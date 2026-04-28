<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Zimbo Socials')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --zim-green: #009e00;
            --zim-gold: #ffd700;
            --zim-red: #ce1126;
            --zim-black: #1a1a1a;
            --zim-white: #ffffff;
            
            --bg: #f5f7fa;
            --ink: var(--zim-black);
            --muted: #666666;
            --brand: var(--zim-green);
            --brand-dark: #007200;
            --card: var(--zim-white);
            --line: #e0e0e0;
            --accent: var(--zim-red);
            --text-muted: #666666;
            --bg-secondary: #f0f8f4;
            --border-color: #e5e7eb;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; color: var(--ink); background: linear-gradient(135deg, #f5f7fa 0%, #e8f5f0 100%); line-height: 1.6; }
        a { color: inherit; text-decoration: none; }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .nav { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding: 18px 0; border-bottom: 2px solid var(--line); position: relative; }
        .brand { font-family: 'Poppins', sans-serif; letter-spacing: -0.5px; font-weight: 800; color: var(--zim-green); font-size: 1.4rem; white-space: nowrap; }
        .brand-logo { height: 38px; width: auto; display: block; }
        .brand-accent { color: var(--zim-red); }
        .nav-desktop { display: flex; gap: 24px; align-items: center; flex-wrap: wrap; }
        .nav-link { font-size: 14px; color: var(--muted); font-weight: 500; transition: color 0.3s; white-space: nowrap; display: inline-flex; align-items: center; }
        .nav-link:hover { color: var(--brand); }
        .nav-mobile-toggle { display: none; flex-direction: column; background: none; border: none; cursor: pointer; gap: 4px; padding: 8px; }
        .nav-mobile-toggle span { width: 24px; height: 2.5px; background: var(--ink); border-radius: 2px; transition: all 0.3s; }
        .nav-mobile-toggle.active span:nth-child(1) { transform: rotate(45deg) translate(10px, 10px); }
        .nav-mobile-toggle.active span:nth-child(2) { opacity: 0; }
        .nav-mobile-toggle.active span:nth-child(3) { transform: rotate(-45deg) translate(7px, -7px); }
        .nav-mobile { display: none; flex-direction: column; gap: 12px; padding: 14px 0; }
        .nav-mobile.active { display: flex; }
        .nav-mobile-actions { display: flex; gap: 10px; flex-direction: column; padding-top: 8px; border-top: 1px solid var(--line); }
        .btn { display: inline-block; border-radius: 8px; padding: 11px 20px; font-weight: 600; font-size: 14px; transition: all 0.3s ease; cursor: pointer; border: none; text-align: center; position: relative; overflow: hidden; }
        .btn::before { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; border-radius: 50%; background: rgba(255,255,255,0.3); transform: translate(-50%, -50%); transition: width 0.6s, height 0.6s; }
        .btn:active::before { width: 300px; height: 300px; }
        .btn-primary { background: var(--zim-green); color: #fff; box-shadow: 0 4px 15px rgba(0, 158, 0, 0.3); }
        .btn-primary:hover { 
            background: var(--brand-dark); 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(0, 158, 0, 0.5); 
        }
        .btn-primary:active { transform: translateY(-1px); }
        .btn-secondary { border: 2px solid var(--zim-green); background: #fff; color: var(--zim-green); }
        .btn-secondary:hover { background: var(--bg-secondary); box-shadow: 0 4px 15px rgba(0, 158, 0, 0.2); transform: translateY(-2px); }

        .hero { padding: 42px 0 26px; }
        .hero h1 { font-size: clamp(30px, 5vw, 52px); line-height: 1.05; max-width: 800px; }
        .hero p { color: var(--muted); max-width: 700px; margin-top: 14px; font-size: 17px; }

        .section { padding: 26px 0 36px; }
        .section h2 { font-size: 26px; margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 16px; transition: all 0.3s; }
        .card:hover { box-shadow: 0 8px 24px rgba(0, 158, 0, 0.12); transform: translateY(-4px); }
        .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 6px 12px; border-radius: 20px; background: #ecf7f3; color: var(--brand); margin-bottom: 10px; }
        .muted { color: var(--muted); }

        .site-footer { background: linear-gradient(135deg, var(--zim-black) 0%, #2a2a2a 100%); color: white; padding: 56px 0 20px; margin-top: 64px; }
        .site-footer-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 32px; margin-bottom: 32px; }
        .site-footer h3, .site-footer h4 { margin-bottom: 14px; }
        .site-footer h3 { font-size: 1.15rem; color: var(--zim-green); }
        .footer-brand-logo { height: 44px; width: auto; display: block; margin-bottom: 14px; }
        .site-footer h4 { font-size: 1rem; color: white; }
        .site-footer p { color: #cfcfcf; font-size: 0.92rem; }
        .site-footer ul { list-style: none; padding: 0; margin: 0; }
        .site-footer li { margin-bottom: 10px; }
        .site-footer a { color: #cfcfcf; transition: color 0.25s ease; }
        .site-footer a:hover { color: var(--zim-green); }
        .social-list { display: flex; gap: 12px; margin-top: 16px; }
        .social-icon { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 50%; background: #373737; color: #fff; border: 1px solid #494949; }
        .social-icon:hover { border-color: var(--zim-green); color: var(--zim-green); }
        .site-footer-bottom { border-top: 1px solid rgba(255,255,255,0.12); padding-top: 16px; text-align: center; color: #9a9a9a; font-size: 0.85rem; }

        footer { border-top: none; margin-top: 0; padding: 0; color: white; font-size: 13px; }

        i { margin-right: 8px; }
        
        @media (max-width: 900px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .site-footer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .container { padding: 0 14px; }
            .nav { gap: 10px; flex-wrap: wrap; }
            .brand-logo { height: 32px; }
            .nav-desktop { display: none; }
            .nav-mobile-toggle { display: flex; }
            .nav-mobile {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 12px 14px;
                margin-top: 8px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                width: calc(100% + 28px);
                margin-left: -14px;
            }
            .nav-link { font-size: 13px; }
            .btn { padding: 10px 14px; font-size: 13px; width: 100%; }
            .grid { grid-template-columns: 1fr; }
            .site-footer { padding: 40px 0 18px; }
            .site-footer-grid { grid-template-columns: 1fr; gap: 26px; }
        }
    </style>
    @yield('head')
</head>
<body>
    <x-marketing.header />

    <main class="container">
        @yield('content')
    </main>

    <x-marketing.footer />
</body>
</html>
