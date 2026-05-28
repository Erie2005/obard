const express = require('express');
const session = require('express-session');
const helmet = require('helmet');
const path = require('path');
const crypto = require('crypto');
const { initializeDatabase } = require('./db/schema');
const createAuthRouter = require('./routes/auth');
const createProductRouter = require('./routes/products');
const createStockRouter = require('./routes/stock');
const { requireAuth } = require('./middleware/auth');

const app = express();
const PORT = process.env.PORT || 3000;

// Initialize database
const db = initializeDatabase();

// Security middleware
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      styleSrc: ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com", "https://cdnjs.cloudflare.com"],
      fontSrc: ["'self'", "https://fonts.gstatic.com", "https://cdnjs.cloudflare.com"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      imgSrc: ["'self'", "data:"],
    },
  },
}));

// Body parsing
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Session configuration
const sessionSecret = process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex');
app.use(session({
  secret: sessionSecret,
  resave: false,
  saveUninitialized: false,
  cookie: {
    secure: false,
    httpOnly: true,
    maxAge: 24 * 60 * 60 * 1000, // 24 hours
    sameSite: 'lax',
  },
}));

// Static files
app.use(express.static(path.join(__dirname, 'public')));

// Routes
app.use('/', createAuthRouter(db));
app.use('/api/products', createProductRouter(db));
app.use('/api/stock', createStockRouter(db));

// Protected page routes
app.get('/dashboard', requireAuth, (req, res) => {
  res.sendFile('dashboard.html', { root: './views' });
});

app.get('/products', requireAuth, (req, res) => {
  res.sendFile('products.html', { root: './views' });
});

app.get('/stock', requireAuth, (req, res) => {
  res.sendFile('stock.html', { root: './views' });
});

// Root redirect
app.get('/', (req, res) => {
  if (req.session && req.session.shopkeeper) {
    return res.redirect('/dashboard');
  }
  res.redirect('/login');
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({ error: 'Page not found' });
});

// Global error handler
app.use((err, req, res, next) => {
  console.error('Server error:', err);
  res.status(500).json({ error: 'Internal server error' });
});

app.listen(PORT, () => {
  console.log(`Stock Management System running at http://localhost:${PORT}`);
  console.log(`Default login: admin@shop.com / Admin@123`);
});

module.exports = app;
