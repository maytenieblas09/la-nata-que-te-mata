const express = require('express');
const mysql = require('mysql2/promise');
const session = require('express-session');
const bcrypt = require('bcryptjs');
const cors = require('cors');
require('dotenv').config();

const path = require('path');
const app = express();
const PORT = 3000;

app.set('trust proxy', 1);
app.use(express.json({ limit: '10kb' }));

app.use(cors({
  origin: 'https://maytenieblas09.github.io',
  credentials: true,
  methods: ['GET', 'POST', 'OPTIONS'],
  allowedHeaders: [
    'Content-Type',
    'ngrok-skip-browser-warning'
  ]
}));

app.use(session({
  secret: process.env.SESSION_SECRET,
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: 'auto',
    maxAge: 1000 * 60 * 60 * 4
  }
}));

const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'nata_que_te_mata',
  waitForConnections: true,
  connectionLimit: 10
});

app.use(express.static(path.join(__dirname, 'public')));

function responder(res, datos, status = 200) {
  return res.status(status).json(datos);
}

function iniciarSesion(req, usuarioId, correo) {
  return new Promise((resolve, reject) => {
    req.session.regenerate(err => {
      if (err) return reject(err);

      req.session.usuario_id = usuarioId;
      req.session.correo = correo;

      req.session.save(err => {
        if (err) return reject(err);
        resolve();
      });
    });
  });
}

// Esta ruta conserva la dirección que usa tu index.html.
app.all('/auth.php', async (req, res) => {
  const action = req.query.action;

  try {
    if (action === 'status' && req.method === 'GET') {
      return responder(res, {
        ok: true,
        autenticado: Boolean(req.session.usuario_id),
        correo: req.session.correo || null
      });
    }

    if (action === 'logout' && req.method === 'GET') {
      return req.session.destroy(err => {
        if (err) {
          return responder(res, {
            ok: false,
            mensaje: 'No se pudo cerrar la sesión.'
          }, 500);
        }

        res.clearCookie('connect.sid');
        return res.redirect('/index.html');
      });
    }

    if (req.method !== 'POST' ||
        !['registro', 'login'].includes(action)) {
      return responder(res, {
        ok: false,
        mensaje: 'Solicitud no válida.'
      }, 405);
    }

    const correo = String(req.body.correo || '')
      .trim().toLowerCase();
    const contrasena = String(req.body.contrasena || '');

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo) ||
        !contrasena) {
      return responder(res, {
        ok: false,
        mensaje: 'Escribe un correo válido y una contraseña.'
      }, 400);
    }

    let usuarioId;

    if (action === 'registro') {
      const [existentes] = await pool.execute(
        'SELECT id FROM usuarios WHERE correo = ?',
        [correo]
      );

      if (existentes.length) {
        return responder(res, {
          ok: false,
          mensaje: 'Ese correo ya está registrado. Inicia sesión.'
        }, 409);
      }

      const hash = await bcrypt.hash(contrasena, 12);

      const [resultado] = await pool.execute(
        'INSERT INTO usuarios (correo, password_hash) VALUES (?, ?)',
        [correo, hash]
      );

      usuarioId = resultado.insertId;
    } else {
      const [usuarios] = await pool.execute(
        'SELECT id, password_hash FROM usuarios WHERE correo = ?',
        [correo]
      );

      if (!usuarios.length) {
        return responder(res, {
          ok: false,
          mensaje: 'Ese correo no está registrado. Crea una cuenta primero.'
        }, 404);
      }

      const usuario = usuarios[0];

      // Compatibilidad con hashes bcrypt creados por PHP.
      const hash = usuario.password_hash.replace(/^\$2y\$/, '$2b$');
      const coincide = await bcrypt.compare(contrasena, hash);

      if (!coincide) {
        return responder(res, {
          ok: false,
          mensaje: 'La contraseña no coincide con esa cuenta.'
        }, 401);
      }

      usuarioId = usuario.id;
    }

    await iniciarSesion(req, usuarioId, correo);

    await pool.execute(
      `INSERT INTO sesiones_registradas
       (usuario_id, correo, ip, navegador)
       VALUES (?, ?, ?, ?)`,
      [
        usuarioId,
        correo,
        req.ip || null,
        String(req.get('user-agent') || '').slice(0, 500)
      ]
    );

    return responder(res, {
      ok: true,
      autenticado: true,
      correo
    });
  } catch (error) {
    console.error('Error del servidor:', error.message);
    return responder(res, {
      ok: false,
      mensaje: 'Error del servidor o de conexión con MySQL.'
    }, 500);
  }
});

app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

async function iniciarServidor() {
  if (!process.env.SESSION_SECRET) {
    console.error('Falta SESSION_SECRET en el archivo .env');
    process.exit(1);
  }

  try {
    await pool.query('SELECT 1');
    console.log('Conexión con MySQL correcta.');

    app.listen(PORT, '0.0.0.0', () => {
      console.log(`La Nata Que Te Mata está en http://localhost:${PORT}`);
    });
  } catch (error) {
    console.error('No se pudo conectar con MySQL:', error.message);
    process.exit(1);
  }
}

iniciarServidor();
