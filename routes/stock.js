const express = require('express');
const { body, param, query, validationResult } = require('express-validator');
const { requireAuthAPI } = require('../middleware/auth');

function createStockRouter(db) {
  const router = express.Router();

  router.use(requireAuthAPI);

  // Record stock-in
  router.post('/in', [
    body('product_id').isInt({ min: 1 }).withMessage('Valid product ID is required'),
    body('quantity').isInt({ min: 1 }).withMessage('Quantity must be at least 1'),
    body('unit_price').isFloat({ min: 0 }).withMessage('Unit price must be a positive number'),
    body('supplier').optional().trim().isLength({ max: 200 }).withMessage('Supplier max 200 characters'),
    body('notes').optional().trim().isLength({ max: 500 }).withMessage('Notes max 500 characters'),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { product_id, quantity, unit_price, supplier, notes } = req.body;
    const shopkeeperId = req.session.shopkeeper.id;

    const product = db.prepare(
      'SELECT * FROM product WHERE id = ? AND shopkeeper_id = ?'
    ).get(product_id, shopkeeperId);

    if (!product) return res.status(404).json({ error: 'Product not found' });

    const transaction = db.transaction(() => {
      db.prepare(`
        INSERT INTO product_in (product_id, quantity, unit_price, supplier, notes, shopkeeper_id)
        VALUES (?, ?, ?, ?, ?, ?)
      `).run(product_id, quantity, unit_price, supplier || null, notes || null, shopkeeperId);

      db.prepare(`
        UPDATE product SET quantity_in_stock = quantity_in_stock + ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND shopkeeper_id = ?
      `).run(quantity, product_id, shopkeeperId);
    });

    transaction();

    const updatedProduct = db.prepare('SELECT * FROM product WHERE id = ?').get(product_id);
    res.status(201).json({
      message: `Stock-in recorded: ${quantity} units added`,
      product: updatedProduct,
    });
  });

  // Record stock-out
  router.post('/out', [
    body('product_id').isInt({ min: 1 }).withMessage('Valid product ID is required'),
    body('quantity').isInt({ min: 1 }).withMessage('Quantity must be at least 1'),
    body('unit_price').isFloat({ min: 0 }).withMessage('Unit price must be a positive number'),
    body('customer').optional().trim().isLength({ max: 200 }).withMessage('Customer max 200 characters'),
    body('notes').optional().trim().isLength({ max: 500 }).withMessage('Notes max 500 characters'),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { product_id, quantity, unit_price, customer, notes } = req.body;
    const shopkeeperId = req.session.shopkeeper.id;

    const product = db.prepare(
      'SELECT * FROM product WHERE id = ? AND shopkeeper_id = ?'
    ).get(product_id, shopkeeperId);

    if (!product) return res.status(404).json({ error: 'Product not found' });

    if (product.quantity_in_stock < quantity) {
      return res.status(400).json({
        error: `Insufficient stock. Available: ${product.quantity_in_stock}, Requested: ${quantity}`,
      });
    }

    const transaction = db.transaction(() => {
      db.prepare(`
        INSERT INTO product_out (product_id, quantity, unit_price, customer, notes, shopkeeper_id)
        VALUES (?, ?, ?, ?, ?, ?)
      `).run(product_id, quantity, unit_price, customer || null, notes || null, shopkeeperId);

      db.prepare(`
        UPDATE product SET quantity_in_stock = quantity_in_stock - ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND shopkeeper_id = ?
      `).run(quantity, product_id, shopkeeperId);
    });

    transaction();

    const updatedProduct = db.prepare('SELECT * FROM product WHERE id = ?').get(product_id);
    res.status(201).json({
      message: `Stock-out recorded: ${quantity} units removed`,
      product: updatedProduct,
    });
  });

  // Get stock records (combined in & out)
  router.get('/records', (req, res) => {
    const shopkeeperId = req.session.shopkeeper.id;

    const stockIn = db.prepare(`
      SELECT pi.*, p.name as product_name, 'IN' as type
      FROM product_in pi
      JOIN product p ON pi.product_id = p.id
      WHERE pi.shopkeeper_id = ?
      ORDER BY pi.recorded_at DESC
    `).all(shopkeeperId);

    const stockOut = db.prepare(`
      SELECT po.*, p.name as product_name, 'OUT' as type
      FROM product_out po
      JOIN product p ON po.product_id = p.id
      WHERE po.shopkeeper_id = ?
      ORDER BY po.recorded_at DESC
    `).all(shopkeeperId);

    const records = [...stockIn, ...stockOut].sort(
      (a, b) => new Date(b.recorded_at) - new Date(a.recorded_at)
    );

    res.json(records);
  });

  // Get stock-in records only
  router.get('/in', (req, res) => {
    const shopkeeperId = req.session.shopkeeper.id;
    const records = db.prepare(`
      SELECT pi.*, p.name as product_name
      FROM product_in pi
      JOIN product p ON pi.product_id = p.id
      WHERE pi.shopkeeper_id = ?
      ORDER BY pi.recorded_at DESC
    `).all(shopkeeperId);
    res.json(records);
  });

  // Get stock-out records only
  router.get('/out', (req, res) => {
    const shopkeeperId = req.session.shopkeeper.id;
    const records = db.prepare(`
      SELECT po.*, p.name as product_name
      FROM product_out po
      JOIN product p ON po.product_id = p.id
      WHERE po.shopkeeper_id = ?
      ORDER BY po.recorded_at DESC
    `).all(shopkeeperId);
    res.json(records);
  });

  // Dashboard stats
  router.get('/dashboard', (req, res) => {
    const shopkeeperId = req.session.shopkeeper.id;

    const totalProducts = db.prepare(
      'SELECT COUNT(*) as count FROM product WHERE shopkeeper_id = ?'
    ).get(shopkeeperId).count;

    const totalStockValue = db.prepare(
      'SELECT COALESCE(SUM(unit_price * quantity_in_stock), 0) as value FROM product WHERE shopkeeper_id = ?'
    ).get(shopkeeperId).value;

    const totalStockIn = db.prepare(
      'SELECT COALESCE(SUM(quantity), 0) as total FROM product_in WHERE shopkeeper_id = ?'
    ).get(shopkeeperId).total;

    const totalStockOut = db.prepare(
      'SELECT COALESCE(SUM(quantity), 0) as total FROM product_out WHERE shopkeeper_id = ?'
    ).get(shopkeeperId).total;

    const lowStockProducts = db.prepare(
      'SELECT * FROM product WHERE shopkeeper_id = ? AND quantity_in_stock <= 5 ORDER BY quantity_in_stock ASC'
    ).all(shopkeeperId);

    const recentActivity = db.prepare(`
      SELECT * FROM (
        SELECT pi.quantity, pi.recorded_at, p.name as product_name, 'IN' as type
        FROM product_in pi JOIN product p ON pi.product_id = p.id
        WHERE pi.shopkeeper_id = ?
        UNION ALL
        SELECT po.quantity, po.recorded_at, p.name as product_name, 'OUT' as type
        FROM product_out po JOIN product p ON po.product_id = p.id
        WHERE po.shopkeeper_id = ?
      ) ORDER BY recorded_at DESC LIMIT 10
    `).all(shopkeeperId, shopkeeperId);

    res.json({
      totalProducts,
      totalStockValue,
      totalStockIn,
      totalStockOut,
      lowStockProducts,
      recentActivity,
    });
  });

  return router;
}

module.exports = createStockRouter;
