const express = require('express');
const { body, param, validationResult } = require('express-validator');
const { requireAuthAPI } = require('../middleware/auth');

function createProductRouter(db) {
  const router = express.Router();

  router.use(requireAuthAPI);

  // Get all products for logged-in shopkeeper
  router.get('/', (req, res) => {
    const products = db.prepare(
      'SELECT * FROM product WHERE shopkeeper_id = ? ORDER BY updated_at DESC'
    ).all(req.session.shopkeeper.id);
    res.json(products);
  });

  // Get single product
  router.get('/:id', [
    param('id').isInt({ min: 1 }).withMessage('Invalid product ID'),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const product = db.prepare(
      'SELECT * FROM product WHERE id = ? AND shopkeeper_id = ?'
    ).get(req.params.id, req.session.shopkeeper.id);

    if (!product) return res.status(404).json({ error: 'Product not found' });
    res.json(product);
  });

  // Add new product
  router.post('/', [
    body('name').trim().notEmpty().withMessage('Product name is required')
      .isLength({ min: 2, max: 200 }).withMessage('Name must be 2-200 characters'),
    body('description').optional().trim().isLength({ max: 500 }).withMessage('Description max 500 characters'),
    body('category').optional().trim().isLength({ max: 100 }).withMessage('Category max 100 characters'),
    body('unit_price').isFloat({ min: 0 }).withMessage('Unit price must be a positive number'),
    body('quantity_in_stock').optional().isInt({ min: 0 }).withMessage('Quantity must be a non-negative integer'),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { name, description, category, unit_price, quantity_in_stock } = req.body;
    const shopkeeperId = req.session.shopkeeper.id;

    const result = db.prepare(`
      INSERT INTO product (name, description, category, unit_price, quantity_in_stock, shopkeeper_id)
      VALUES (?, ?, ?, ?, ?, ?)
    `).run(name, description || null, category || null, unit_price, quantity_in_stock || 0, shopkeeperId);

    const product = db.prepare('SELECT * FROM product WHERE id = ?').get(result.lastInsertRowid);
    res.status(201).json({ message: 'Product added successfully', product });
  });

  // Update product
  router.put('/:id', [
    param('id').isInt({ min: 1 }).withMessage('Invalid product ID'),
    body('name').trim().notEmpty().withMessage('Product name is required')
      .isLength({ min: 2, max: 200 }).withMessage('Name must be 2-200 characters'),
    body('description').optional().trim().isLength({ max: 500 }).withMessage('Description max 500 characters'),
    body('category').optional().trim().isLength({ max: 100 }).withMessage('Category max 100 characters'),
    body('unit_price').isFloat({ min: 0 }).withMessage('Unit price must be a positive number'),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { name, description, category, unit_price } = req.body;
    const shopkeeperId = req.session.shopkeeper.id;

    const existing = db.prepare(
      'SELECT * FROM product WHERE id = ? AND shopkeeper_id = ?'
    ).get(req.params.id, shopkeeperId);

    if (!existing) return res.status(404).json({ error: 'Product not found' });

    db.prepare(`
      UPDATE product SET name = ?, description = ?, category = ?, unit_price = ?, updated_at = CURRENT_TIMESTAMP
      WHERE id = ? AND shopkeeper_id = ?
    `).run(name, description || null, category || null, unit_price, req.params.id, shopkeeperId);

    const product = db.prepare('SELECT * FROM product WHERE id = ?').get(req.params.id);
    res.json({ message: 'Product updated successfully', product });
  });

  // Delete product
  router.delete('/:id', [
    param('id').isInt({ min: 1 }).withMessage('Invalid product ID'),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const shopkeeperId = req.session.shopkeeper.id;

    const existing = db.prepare(
      'SELECT * FROM product WHERE id = ? AND shopkeeper_id = ?'
    ).get(req.params.id, shopkeeperId);

    if (!existing) return res.status(404).json({ error: 'Product not found' });

    db.prepare('DELETE FROM product WHERE id = ? AND shopkeeper_id = ?').run(req.params.id, shopkeeperId);
    res.json({ message: 'Product deleted successfully' });
  });

  return router;
}

module.exports = createProductRouter;
