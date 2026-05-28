function requireAuth(req, res, next) {
  if (!req.session || !req.session.shopkeeper) {
    return res.redirect('/login');
  }
  res.locals.shopkeeper = req.session.shopkeeper;
  next();
}

function requireAuthAPI(req, res, next) {
  if (!req.session || !req.session.shopkeeper) {
    return res.status(401).json({ error: 'Unauthorized. Please log in.' });
  }
  next();
}

function redirectIfAuth(req, res, next) {
  if (req.session && req.session.shopkeeper) {
    return res.redirect('/dashboard');
  }
  next();
}

module.exports = { requireAuth, requireAuthAPI, redirectIfAuth };
