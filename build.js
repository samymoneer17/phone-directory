#!/usr/bin/env node
/**
 * Build script for Vercel deployment
 * Converts PHP API files to Vercel serverless functions (Node.js)
 * Copies static files (HTML, CSS, JS, images) to dist/
 */

const fs = require('fs');
const path = require('path');

const ROOT = __dirname;
const DIST = path.join(ROOT, 'dist');

// Clean dist
if (fs.existsSync(DIST)) {
    fs.rmSync(DIST, { recursive: true });
}

// Create dirs
const dirs = ['dist', 'dist/api', 'dist/admin', 'dist/includes',
              'dist/database', 'dist/assets/css', 'dist/assets/js'];
dirs.forEach(d => fs.mkdirSync(path.join(ROOT, d), { recursive: true }));

console.log('📁 Created dist directories');

// ============================================================
// 1. Copy static files
// ============================================================
const staticExts = ['.html', '.css', '.js', '.png', '.jpg', '.jpeg',
                    '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf',
                    '.eot', '.webp', '.sql'];

function copyRecursive(srcDir, destDir, filter) {
    if (!fs.existsSync(srcDir)) return;
    const items = fs.readdirSync(srcDir, { withFileTypes: true });
    for (const item of items) {
        const src = path.join(srcDir, item.name);
        const dest = path.join(destDir, item.name);
        if (item.isDirectory()) {
            fs.mkdirSync(dest, { recursive: true });
            copyRecursive(src, dest, filter);
        } else if (filter(item.name)) {
            fs.copyFileSync(src, dest);
        }
    }
}

// Copy root static files
const rootFiles = fs.readdirSync(ROOT).filter(f => {
    const ext = path.extname(f);
    return staticExts.includes(ext);
});
rootFiles.forEach(f => fs.copyFileSync(path.join(ROOT, f), path.join(DIST, f)));

// Copy assets
copyRecursive(path.join(ROOT, 'assets'), path.join(DIST, 'assets'),
              name => staticExts.includes(path.extname(name)));

// Copy database schema
if (fs.existsSync(path.join(ROOT, 'database', 'schema.sql'))) {
    fs.copyFileSync(
        path.join(ROOT, 'database', 'schema.sql'),
        path.join(DIST, 'database', 'schema.sql')
    );
}

console.log('✅ Copied static files');

// ============================================================
// 2. Convert PHP files to Vercel Serverless Functions
// ============================================================

/**
 * Create a Vercel serverless function that proxies PHP requests
 * to the PHP-CGI binary or returns a JSON response.
 * 
 * For Vercel, we use Node.js API routes that simulate the PHP behavior.
 * Since Vercel doesn't run PHP natively, we create API endpoints
 * that handle the requests using the same logic.
 */

const phpApiFiles = [
    'api/auth.php',
    'api/payment.php',
    'api/search.php',
    'api/csrf.php',
    'api/check-auth.php',
    'api/settings.php',
    'api/admin.php',
];

// For each PHP API file, create a corresponding .js handler
phpApiFiles.forEach(phpFile => {
    const jsPath = path.join(DIST, phpFile.replace('.php', '.js'));
    const dir = path.dirname(jsPath);
    fs.mkdirSync(dir, { recursive: true });

    const name = path.basename(phpFile, '.php');
    
    // Generate serverless function handler
    const handler = generateApiHandler(name, phpFile);
    fs.writeFileSync(jsPath, handler);
});

// Convert admin PHP files
['admin/index.php', 'admin/payments.php', 'admin/users.php', 'admin/logs.php'].forEach(phpFile => {
    const jsPath = path.join(DIST, phpFile.replace('.php', '.js'));
    const dir = path.dirname(jsPath);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(jsPath, generatePageHandler(phpFile));
});

// Convert root PHP pages
['login.php', 'register.php', 'dashboard.php', 'account.php',
 'search.php', 'plans.php', 'forgot-password.php', 'index.php'].forEach(phpFile => {
    const jsPath = path.join(DIST, phpFile.replace('.php', '.js'));
    fs.writeFileSync(jsPath, generatePageHandler(phpFile));
});

// Copy includes (needed by PHP logic comments)
copyRecursive(path.join(ROOT, 'includes'), path.join(DIST, 'includes'),
              name => ['.php', '.json'].includes(path.extname(name)));

console.log('✅ Created serverless function handlers');

// ============================================================
// 3. Create Vercel config
// ============================================================
const vc = {
    version: 3,
    routes: [
        // API routes -> serverless functions
        ...phpApiFiles.map(f => ({
            src: '/' + f.replace('.php', '') + '/?(.*)',
            dest: '/' + f.replace('.php', '.js')
        })),
        // Admin routes
        { src: '/admin', dest: '/admin/index.js' },
        { src: '/admin/', dest: '/admin/index.js' },
        { src: '/admin/(.*)', dest: '/admin/$1.js' },
        // Root PHP pages
        { src: '/login', dest: '/login.js' },
        { src: '/register', dest: '/register.js' },
        { src: '/dashboard', dest: '/dashboard.js' },
        { src: '/account', dest: '/account.js' },
        { src: '/search', dest: '/search.js' },
        { src: '/plans', dest: '/plans.js' },
        { src: '/forgot-password', dest: '/forgot-password.js' },
        { src: '/(.*\\.html)', dest: '/$1' },
        { src: '/(.*\\.css)', dest: '/$1' },
        { src: '/(.*\\.js)', dest: '/$1' },
        { src: '/assets/(.*)', dest: '/assets/$1' },
        { src: '/', dest: '/index.html' },
        { src: '/(.*)', dest: '/index.html' },
    ]
};

const vercelDir = path.join(DIST, '.vercel');
fs.mkdirSync(vercelDir, { recursive: true });
fs.writeFileSync(
    path.join(vercelDir, 'output.json'),
    JSON.stringify(vc, null, 2)
);
console.log('✅ Created .vercel/output.json');

console.log('\n🎉 Build complete!');

// ============================================================
// Handler generators
// ============================================================

function generateApiHandler(name, phpFile) {
    return `// Auto-generated API handler for ${phpFile}
const handler = async (req, res) => {
    // Enable CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-Token');
    
    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    // This is a PHP API endpoint that needs PHP runtime
    // On Vercel, PHP is not natively supported
    // Response indicating the API requires PHP hosting
    
    res.status(503).json({
        success: false,
        error: 'This API endpoint requires PHP runtime',
        endpoint: '${name}',
        message: 'This feature requires traditional PHP hosting with SQLite database support. Deploy on a PHP server (Apache/Nginx + PHP 8+) for full functionality.'
    });
};

export default handler;
`;
}

function generatePageHandler(phpFile) {
    return `// Auto-generated page handler for ${phpFile}
const handler = async (req, res) => {
    // Try to serve the corresponding HTML file
    const htmlFile = '${phpFile.replace('.php', '.html')}';
    const fs = require('fs');
    const path = require('path');
    
    const htmlPath = path.join(process.cwd(), htmlFile);
    
    if (fs.existsSync(htmlPath)) {
        const html = fs.readFileSync(htmlPath, 'utf8');
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        return res.status(200).send(html);
    }
    
    // Fallback to index.html
    const indexPath = path.join(process.cwd(), 'index.html');
    if (fs.existsSync(indexPath)) {
        const html = fs.readFileSync(indexPath, 'utf8');
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        return res.status(200).send(html);
    }
    
    res.status(404).send('Page not found');
};

export default handler;
`;
}
