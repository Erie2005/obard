const express = require('express');
const bcrypt = require('bcryptjs');
const { body, validationResult } = require('express-validator');
const { redirectIfAuth } = require('../middleware/auth');

function createAuthRouter(db) {
  const router = express.Router();

  router.get('/login', redirectIfAuth, (req, res) => {
    res.sendFile('login.html', { root: './views' });
  });

  router.get('/register', redirectIfAuth, (req, res) => {
    res.sendFile('register.html', { root: './views' });
  });

  router.post('/api/auth/login', [
    body('email').isEmail().normalizeEmail().withMessage('Valid email is required'),
    body('password').notEmpty().withMessage('Password is required'),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { email, password } = req.body;

    const shopkeeper = db.prepare('SELECT * FROM shopkeeper WHERE email = ?').get(email);
    if (!shopkeeper) {
      return res.status(401).json({ error: 'Invalid email or password' });
    }

    const isMatch = bcrypt.compareSync(password, shopkeeper.password);
    if (!isMatch) {
      return res.status(401).json({ error: 'Invalid email or password' });
    }

    req.session.shopkeeper = {
      id: shopkeeper.id,
      full_name: shopkeeper.full_name,
      email: shopkeeper.email,
      phone: shopkeeper.phone,
    };

    res.json({ message: 'Login successful', shopkeeper: req.session.shopkeeper });
  });

  router.post('/api/auth/register', [
    body('full_name').trim().notEmpty().withMessage('Full name is required')
      .isLength({ min: 2, max: 100 }).withMessage('Name must be 2-100 characters'),
    body('email').isEmail().normalizeEmail().withMessage('Valid email is required'),
    body('phone').optional().trim().matches(/^\+?[0-9]{7,15}$/).withMessage('Invalid phone number'),
    body('password').isLength({ min: 8 }).withMessage('Password must be at least 8 characters')
      .matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])/)
      .withMessage('Password must contain uppercase, lowercase, number, and special character'),
    body('confirm_password').custom((value, { req }) => {
      if (value !== req.body.password) throw new Error('Passwords do not match');
      return true;
    }),
  ], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { full_name, email, phone, password } = req.body;

    const existing = db.prepare('SELECT id FROM shopkeeper WHERE email = ?').get(email);
    if (existing) {
      return res.status(409).json({ error: 'Email already registered' });
    }

    const hashedPassword = bcrypt.hashSync(password, 10);
    const result = db.prepare(
      'INSERT INTO shopkeeper (full_name, email, phone, password) VALUES (?, ?, ?, ?)'
    ).run(full_name, email, phone || null, hashedPassword);

    req.session.shopkeeper = {
      id: result.lastInsertRowid,
      full_name,
      email,
      phone: phone || null,
    };

    res.status(201).json({ message: 'Registration successful', shopkeeper: req.session.shopkeeper });
  });

  router.post('/api/auth/logout', (req, res) => {
    req.session.destroy((err) => {
      if (err) return res.status(500).json({ error: 'Logout failed' });
      res.clearCookie('connect.sid');
      res.json({ message: 'Logged out successfully' });
    });
  });

  return router;
}

module.exports = createAuthRouter;
