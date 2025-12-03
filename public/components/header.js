// Global Navigation Header Component
function createNavigationHeader(activePage = '') {
    const headerHTML = `
        <style>
            .global-nav {
                background: white;
                padding: 15px 30px;
                border-radius: 10px;
                margin-bottom: 20px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 15px;
            }
            .nav-brand {
                font-size: 20px;
                font-weight: 700;
                color: #667eea;
                text-decoration: none;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .nav-brand:hover { color: #5568d3; }
            .nav-links {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
            }
            .nav-link {
                padding: 8px 16px;
                border-radius: 6px;
                text-decoration: none;
                color: #666;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .nav-link:hover {
                background: #f3f4f6;
                color: #333;
            }
            .nav-link.active {
                background: #667eea;
                color: white;
            }
            @media (max-width: 768px) {
                .global-nav { flex-direction: column; align-items: stretch; }
                .nav-links { flex-direction: column; }
                .nav-link { justify-content: center; }
            }
        </style>
        <nav class="global-nav">
            <a href="/" class="nav-brand">
                <span>🏭</span>
                <span>Micron Tracking</span>
            </a>
            <div class="nav-links">
                <a href="/" class="nav-link ${activePage === 'dashboard' ? 'active' : ''}">
                    <span>📊</span> Dashboard
                </a>
                <a href="/admin.html" class="nav-link ${activePage === 'admin' ? 'active' : ''}">
                    <span>📝</span> Create PO
                </a>
                <a href="/po_dashboard.html" class="nav-link ${activePage === 'tracking' ? 'active' : ''}">
                    <span>📦</span> PO Tracking
                </a>
                <a href="/scanner.html" class="nav-link ${activePage === 'scanner' ? 'active' : ''}">
                    <span>📱</span> Scanner
                </a>
                <a href="/movement_history.html" class="nav-link ${activePage === 'movements' ? 'active' : ''}">
                    <span>📜</span> Movement History
                </a>
            </div>
        </nav>
    `;
    return headerHTML;
}

// Insert header into page
function initNavigation(activePage) {
    const container = document.querySelector('.container');
    if (container) {
        container.insertAdjacentHTML('afterbegin', createNavigationHeader(activePage));
    }
}