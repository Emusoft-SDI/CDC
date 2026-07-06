<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NATCODEV - Your Gateway to Innovation in Coconut Farming</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/forms.js"></script>
    <style>
        :root {
            --primary-color: #1a5276;
            --secondary-color: #27ae60;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --accent-color: #e74c3c;
            --text-color: #34495e;
            --muted-color: #667085;
            --surface-color: #ffffff;
            --light-gray: #ecf0f1;
            --border-color: #bdc3c7;
            --shadow-sm: 0 8px 24px rgba(16, 24, 40, 0.08);
            --shadow-md: 0 18px 42px rgba(16, 24, 40, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #f6f8f7;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            gap: 24px;
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            height: 50px;
            margin-right: 10px;
        }

        .logo-text {
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary-color);
            line-height: 1.2;
        }

        nav ul {
            display: flex;
            list-style: none;
            align-items: center;
            gap: 4px;
        }

        nav li {
            margin-left: 0;
        }

        nav a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        nav a:hover, nav a.active {
            background-color: var(--secondary-color);
            color: white;
        }

        .login-btn {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }

        .login-btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            position: relative;
            height: 600px;
            overflow: hidden;
            color: white;
        }

        .hero-slider {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: cover;
            background-position: center;
        }

        .slide.active {
            opacity: 1;
        }

        .slide-content {
            text-align: center;
            max-width: 800px;
            padding: 0 20px;
            z-index: 2;
        }

        .slide-content h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .slide-content p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        .hero-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            display: flex;
            gap: 10px;
        }

        .hero-control {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .hero-control.active {
            background-color: white;
        }

        .hero-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            z-index: 3;
            padding: 0 20px;
        }

        .hero-nav-btn {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: background-color 0.3s ease;
        }

        .hero-nav-btn:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }

        /* Partners Section */
        .partners {
            padding: 60px 0;
            background-color: var(--light-color);
        }

        .section-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 40px;
            color: var(--primary-color);
        }

        .partners-grid {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 40px;
        }

        .partner-logo {
            width: 150px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .partner-logo:hover {
            transform: scale(1.05);
        }

        .partner-logo img {
            max-width: 120px;
            max-height: 80px;
            object-fit: contain;
        }

        /* Partnership Section */
        .partnership {
            padding: 60px 0;
            background-color: white;
        }

        .partnership-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .partnership-list {
            margin-top: 30px;
            width: 100%;
        }

        .partnership-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 20px;
            background-color: var(--light-gray);
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .partnership-item:hover {
            transform: translateX(5px);
        }

        .check-icon {
            color: var(--secondary-color);
            font-size: 1.5rem;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .partnership-item h3 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        /* Registration Section */
        .registration {
            padding: 72px 0;
            background-color: #f6f8f7;
        }

        .section-intro {
            max-width: 780px;
            margin: -18px auto 34px;
            text-align: center;
            color: var(--muted-color);
            font-size: 1.02rem;
        }

        .registration-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            margin-top: 30px;
        }

        .reg-card {
            background-color: var(--surface-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(16, 24, 40, 0.08);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .reg-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(39, 174, 96, 0.35);
        }

        .reg-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .reg-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .reg-card:hover .reg-image img {
            transform: scale(1.05);
        }

        .reg-content {
            padding: 20px;
            display: flex;
            flex: 1;
            flex-direction: column;
        }

        .reg-content h3 {
            margin-bottom: 15px;
            color: var(--primary-color);
            line-height: 1.25;
        }

        .reg-content p {
            color: var(--muted-color);
            font-size: 0.95rem;
            flex: 1;
        }

        .reg-btn {
            display: inline-block;
            background-color: var(--secondary-color);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 15px;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 600;
        }

        .reg-btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .platform-access {
            padding: 72px 0;
            background: #ffffff;
        }

        .access-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .access-card {
            border: 1px solid rgba(16, 24, 40, 0.08);
            border-radius: 8px;
            background: var(--surface-color);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .access-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: rgba(26, 82, 118, 0.25);
        }

        .access-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #fff;
            background: var(--primary-color);
            margin-bottom: 14px;
        }

        .access-card h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .access-card p {
            color: var(--muted-color);
            flex: 1;
            font-size: 0.95rem;
        }

        .access-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .access-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 9px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            color: #fff;
            background: var(--secondary-color);
        }

        .access-link.secondary {
            color: var(--primary-color);
            background: #eef7f1;
            border: 1px solid rgba(39, 174, 96, 0.24);
        }

        .access-link:hover {
            text-decoration: none;
            transform: translateY(-1px);
        }

        /* Contact Section */
        .contact {
            padding: 60px 0;
            background: linear-gradient(rgba(26, 82, 118, 0.9), rgba(26, 82, 118, 0.9)), url('https://images.unsplash.com/photo-1589927986083-081854841d05?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80') no-repeat center center;
            background-size: cover;
            color: white;
        }

        .contact-container {
            display: flex;
            flex-wrap: wrap;
            gap: 40px;
            align-items: center;
        }

        .contact-image {
            flex: 1;
            min-width: 300px;
        }

        .contact-image img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .contact-form {
            flex: 1;
            min-width: 300px;
            background-color: rgba(255, 255, 255, 0.95);
            color: var(--text-color);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 2px rgba(39, 174, 96, 0.2);
        }

        .submit-btn {
            width: 100%;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        /* Newsletter Section */
        .newsletter {
            padding: 40px 0;
            background-color: white;
            text-align: center;
        }

        .newsletter h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .newsletter-form {
            display: flex;
            justify-content: center;
            max-width: 600px;
            margin: 0 auto;
            gap: 10px;
        }

        .newsletter-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }

        .newsletter-btn {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .newsletter-btn:hover {
            background-color: #218838;
        }

        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 40px 0 20px;
        }

        .footer-container {
            display: flex;
            flex-wrap: wrap;
            gap: 40px;
            justify-content: space-between;
        }

        .footer-col {
            flex: 1;
            min-width: 250px;
        }

        .footer-col h4 {
            margin-bottom: 20px;
            color: var(--secondary-color);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .copyright {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            color: #bdc3c7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                padding: 10px 0;
            }
            
            nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            nav li {
                margin: 5px 10px;
            }
            
            .hero {
                height: 400px;
            }
            
            .slide-content h1 {
                font-size: 2rem;
            }
            
            .slide-content p {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }

            .registration-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .access-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            
            .contact-container {
                flex-direction: column;
            }
            
            .newsletter-form {
                flex-direction: column;
            }
            
            .footer-container {
                flex-direction: column;
            }
        }

        @media (max-width: 640px) {
            .registration-grid {
                grid-template-columns: 1fr;
            }

            .access-grid {
                grid-template-columns: 1fr;
            }

            .reg-content {
                padding: 18px;
            }
        }

        /* Animation for smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Loading spinner */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--light-gray);
            border-top: 5px solid var(--secondary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Scroll animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Form validation styles */
        .form-control.invalid {
            border-color: var(--accent-color);
        }

        .error-message {
            color: var(--accent-color);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }

        .success-message {
            color: var(--secondary-color);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }

        /* Button with loading state */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-color);
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--accent-color);
        }

        .modal-title {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-btn {
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .modal-btn-primary {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }

        .modal-btn-secondary {
            background-color: #bdc3c7;
            color: var(--text-color);
            border: none;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #218838;
        }

    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner">
        <div class="spinner"></div>
    </div>

    <!-- Header -->
    <header>
        <div class="container header-container">
            <div class="logo">
                <img src="https://natcodev.com.ng/images/logo.jpg" alt="Natcodev Logo">
                <span class="logo-text">NATCODEV Coconut Farmers Registry</span>
            </div>
            <nav>
                <ul>
                    <li><a href="#home" class="active">Home</a></li>
                    <li><a href="#registration">Farmers</a></li>
                    <li><a href="https://investors.natcodev.com.ng">Investors</a></li>
                    <li><a href="#registration">Service Providers</a></li>
                    <li><a href="#platform-access">Portals</a></li>
                    <li><a href="recruitment.php">Recruitment</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ul>
            </nav>
            <a class="login-btn" href="dashboard/login.php">LOGIN</a>
        </div>
    </header>

    <!-- Hero Section with Carousel -->
    <section id="home" class="hero">
        <div class="hero-slider">
            <!-- Slide 1 -->
            <div class="slide active" style="background-image: url('https://natcodev.com.ng/images/hero1.jpg')">
                <div class="slide-content">
                    <h1>Your Gateway to Innovation in Coconut Farming</h1>
                    <p>A community of farmers, investors, and dedicated individuals committed to transforming the coconut industry in Nigeria. Take the first step towards a sustainable and prosperous future by registering with Natcodev.</p>
                </div>
            </div>
            
            <!-- Slide 2 -->
            <div class="slide" style="background-image: url('https://natcodev.com.ng/images/hero3.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80')">
                <div class="slide-content">
                    <h1>Empowering Farmers Through Technology</h1>
                    <p>Join our network of innovative coconut farmers who are using modern techniques to increase yields, improve quality, and access new markets across Nigeria and beyond.</p>
                </div>
            </div>
            
            <!-- Slide 3 -->
            <div class="slide" style="background-image: url('https://natcodev.com.ng/images/hero6.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80')">
                <div class="slide-content">
                    <h1>Invest in Sustainable Agriculture</h1>
                    <p>Discover investment opportunities in the growing coconut industry. Support sustainable farming practices while achieving attractive returns on your investment.</p>
                </div>
            </div>
            
            <!-- Slide 4 -->
            <div class="slide" style="background-image: url('https://natcodev.com.ng/images/hero8.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80')">
                <div class="slide-content">
                    <h1>Connect with Industry Experts</h1>
                    <p>Access a network of agricultural service providers, input suppliers, and market experts who can help you succeed in the coconut farming industry.</p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Controls -->
        <div class="hero-nav">
            <button class="hero-nav-btn prev-slide"><i class="fas fa-chevron-left"></i></button>
            <button class="hero-nav-btn next-slide"><i class="fas fa-chevron-right"></i></button>
        </div>
        
        <!-- Dots Indicator -->
        <div class="hero-controls">
            <div class="hero-control active" data-slide="0"></div>
            <div class="hero-control" data-slide="1"></div>
            <div class="hero-control" data-slide="2"></div>
            <div class="hero-control" data-slide="3"></div>
        </div>
    </section>

    <!-- Partners Section -->
    <section class="partners">
        <div class="container">
            <h2 class="section-title">Our Partners</h2>
            <div class="partners-grid">
                <div class="partner-logo">
                    <img src="https://natcodev.com.ng/images/boa.png" alt="Bank of Agriculture">
                </div>
                <div class="partner-logo">
                    <img src="https://natcodev.com.ng/images/fme.jpeg" alt="Federal Ministry of Enviroment">
                </div>
                <div class="partner-logo">
                    <img src="https://natcodev.com.ng/images/fmard.jpeg" alt="Federal Ministry of Agriculture...">
                </div>
                <div class="partner-logo">
                    <img src="https://natcodev.com.ng/images/nirsal.jpeg" alt="NIRSAL">
                </div>
                <div class="partner-logo">
                    <img src="https://natcodev.com.ng/images/cbn.png" alt="CBN">
                </div>
                <div class="partner-logo">
                    <img src="https://natcodev.com.ng/images/natcodev.jpeg" alt="NATCODEV">
                </div>
                            </div>
        </div>
    </section>

    <!-- Partnership Section -->
    <section class="partnership">
        <div class="container">
            <h2 class="section-title">THE PARTNERSHIP</h2>
            <div class="partnership-content">
                <div class="partnership-list">
                    <div class="partnership-item animate-on-scroll">
                        <div class="check-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3>Collaboration</h3>
                            <p>With the Federal Government, State and Local Government, Community Heads, Land Owners and Farmers.</p>
                        </div>
                    </div>
                    <div class="partnership-item animate-on-scroll">
                        <div class="check-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3>Cooperation</h3>
                            <p>With relevant National Agencies, Institutions and the organized Private Sectors.</p>
                        </div>
                    </div>
                    <div class="partnership-item animate-on-scroll">
                        <div class="check-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3>Coordination</h3>
                            <p>With Associations, Multi-Purpose Cooperative Societies, NGOs and Farming Clusters across the nation.</p>
                        </div>
                    </div>
                    <div class="partnership-item animate-on-scroll">
                        <div class="check-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3>Connection</h3>
                            <p>With Financial Institutions, Research Institutes, Technological Infrastructure, Local and Global Markets.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section id="registration" class="registration">
        <div class="container">
            <h2 class="section-title">NATCODEV Coconut Farmers and Stakeholders Registry</h2>
            <p class="section-intro">Becoming part of NATCODEV is quick and easy. Choose the registration path that matches your role, then follow the guided steps to create your account.</p>
            
            <div class="registration-grid">
                <!-- Farmers Registration -->
                <div class="reg-card animate-on-scroll">
                    <div class="reg-image">
                        <img src="https://natcodev.com.ng/images/26.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Farmers Registration">
                    </div>
                    <div class="reg-content">
                        <h3>Coconut Farmers Registration</h3>
                        <p>Elevate your farming experience with NATCODEV. NATCODEV’s Coconut Farmers Registration Program provides a structured platform for integrating coconut producers into a standardized and commercially viable production system. Registered farmers benefit from access to improved planting materials, data-driven agronomic practices, extension and advisory services, and capacity-building programs aligned with industry standards. The initiative supports productivity optimization, quality assurance, and market integration, enabling farmers to operate efficiently, improve yield performance, and contribute to a sustainable and competitive coconut value chain..</p>
                        <a href="apply.php?type=farmer" class="reg-btn">Register as Coconut Farmer</a>
                    </div>
                </div>
                <!-- Commercial Coconut OutGrowers Registration -->
                <div class="reg-card animate-on-scroll">
                    <div class="reg-image">
                        <img src="https://natcodev.com.ng/images/nuts.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Farmers Registration">
                    </div>
                    <div class="reg-content">
                        <h3>Commercial Coconut OutGrowers Registration</h3>
                        <p>The NATCODEV Coconut Out-Growers Scheme is a structured agribusiness initiative designed to integrate smallholder and commercial coconut farmers into a sustainable value chain. Registered out-growers benefit from access to certified high-yield coconut varieties, technical extension services, continuous capacity development, off-take market linkages to ensure efficiency, and predictable returns on investment. By participating, out-growers contribute to scalable coconut production, supply chain stability, and the long-term development of Nigeria’s coconut agribusiness sector.</p>
                        <a href="apply.php?type=outgrower" class="reg-btn">Register as Commercial Coconut OutGrowers</a>
                    </div>
                </div>

                <!-- Coconut Farmers Cooperative Registration -->
                <div class="reg-card animate-on-scroll">
                    <div class="reg-image">
                        <img src="https://natcodev.com.ng/images/coco1.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Farmers Registration">
                    </div>
                    <div class="reg-content">
                        <h3>Coconut Farmers Cooperative Registration</h3>
                        <p>Our cooperative framework enhances the financial performance of coconut farmers through collective production, cost optimization, and coordinated market engagement. Registered cooperative members benefit from certified inputs at reduced costs, access to technical and extension services, structured off-take arrangements, and predictable cash flow. Members gain improved access to credit, input financing, insurance products, investment partnerships, reduced risk, and long-term financial sustainability within the coconut value chain.</p>
                        <a href="https://cfc.natcodev.com.ng" class="reg-btn">Register as Coconut Farmers Cooperative Registration</a>
                    </div>
                </div>

                <!-- Investor Registration -->
                <div class="reg-card animate-on-scroll">
                    <div class="reg-image">
                        <img src="https://natcodev.com.ng/images/23.jpg??ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Investor Registration">
                    </div>
                    <div class="reg-content">
                        <h3>Investor Registration</h3>
                        <p>Invest in the future of agriculture with NATCODEV. As an investor, you have the opportunity to support groundbreaking initiatives in coconut farming. By registering as an investor, you'll gain access to unique investment opportunities, valuable insights, and a network of like-minded individuals dedicated to sustainable agriculture.</p>
                        <a href="https://investors.natcodev.com.ng" class="reg-btn">Register as Investor</a>
                    </div>
                </div>
                
                <!-- Agricultural Services Providers -->
                <div class="reg-card animate-on-scroll">
                    <div class="reg-image">
                        <img src="https://natcodev.com.ng/images/28.jpg??ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Agricultural Services Providers">
                    </div>
                    <div class="reg-content">
                        <h3>Agricultural Services Providers registration</h3>
                        <p>If you're an Agricultural Service Provider committed to delivering top-notch services to farmers, we invite you to register with us. As a registered service provider, you'll have the opportunity to connect with farmers, showcase your expertise, and contribute to the growth of the coconut farming industry in Nigeria.</p>
                        <a href="apply.php?type=service-provider" class="reg-btn">Register as Service Provider</a>
                    </div>
                </div>
                
                <!-- Agricultural Input Providers -->
                <div class="reg-card animate-on-scroll">
                    <div class="reg-image">
                        <img src="https://natcodev.com.ng/images/24.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Agricultural Input Providers">
                    </div>
                    <div class="reg-content">
                        <h3>Agricultural Input providers registration</h3>
                        <p>Are you a supplier of quality agricultural inputs such as seeds, fertilizers, or equipment? NATCODEV welcomes Agricultural Input Providers to register and become a valuable part of our network. Join us in ensuring that farmers have access to the best inputs for sustainable and productive coconut farming.</p>
                        <a href="apply.php?type=input-provider" class="reg-btn">Register as Input Provider</a>
                    </div>
                </div>
                <div class="reg-card animate-on-scroll">
                    <div class="reg-image">
                        <img src="https://natcodev.com.ng/images/28.jpg??ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Field Network Recruitment">
                    </div>
                    <div class="reg-content">
                        <h3>Field Network Recruitment</h3>
                        <p>Apply to join NATCODEV as a Field Agent, Agronomist, or Agric Extensionist. Recruitment applications are reviewed by the admin team before dashboard access and field assignments are created.</p>
                        <a href="recruitment.php" class="reg-btn">Apply for Field Network Role</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="platform-access" class="platform-access">
        <div class="container">
            <h2 class="section-title">Platform Entry Points</h2>
            <p class="section-intro">Choose the portal that matches your role. Access remains protected by login, role permissions, and state or national scope.</p>
            <div class="access-grid">
                <article class="access-card animate-on-scroll">
                    <div class="access-icon"><i class="fas fa-seedling"></i></div>
                    <h3>Growers & Farmers</h3>
                    <p>Manage farm profile, farm health, field status, agronomy requests, wallet, documents, and certification.</p>
                    <div class="access-actions">
                        <a class="access-link" href="dashboard/login.php">Open Dashboard</a>
                        <a class="access-link secondary" href="apply.php?type=farmer">Register</a>
                    </div>
                </article>
                <article class="access-card animate-on-scroll">
                    <div class="access-icon"><i class="fas fa-user-shield"></i></div>
                    <h3>Admin & Coordinators</h3>
                    <p>Access operations dashboards for applications, state coordination, national oversight, resources, communication, and reports.</p>
                    <div class="access-actions">
                        <a class="access-link" href="admin/admin.php">Admin Portal</a>
                        <a class="access-link secondary" href="admin/coordination.php">Role Dashboard</a>
                    </div>
                </article>
                <article class="access-card animate-on-scroll">
                    <div class="access-icon"><i class="fas fa-map-location-dot"></i></div>
                    <h3>Field Network</h3>
                    <p>Field agents, agronomists, and extensionists can access visits, GPS capture, field observations, and advisory workflows.</p>
                    <div class="access-actions">
                        <a class="access-link" href="field-agent/index.php">Field Console</a>
                        <a class="access-link secondary" href="recruitment.php">Apply</a>
                    </div>
                </article>
                <article class="access-card animate-on-scroll">
                    <div class="access-icon"><i class="fas fa-store"></i></div>
                    <h3>Providers & Inputs</h3>
                    <p>Agricultural service and input providers can register for review and listing in the NATCODEV ecosystem.</p>
                    <div class="access-actions">
                        <a class="access-link" href="apply.php?type=service-provider">Service Provider</a>
                        <a class="access-link secondary" href="apply.php?type=input-provider">Input Provider</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <div class="contact-container">
                <div class="contact-image">
                    <img src="https://natcodev.com.ng/images/25.jpg?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Coconut Farming">
                </div>
                <div class="contact-form">
                    <h2>Ready to embark on a journey of growth and innovation? Join NATCODEV today!</h2>
                    <form id="contactForm">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" class="form-control" required>
                            <div class="error-message" id="firstNameError">Please enter your first name</div>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" class="form-control" required>
                            <div class="error-message" id="lastNameError">Please enter your last name</div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" class="form-control" required>
                            <div class="error-message" id="emailError">Please enter a valid email address</div>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="message">Comment / Question *</label>
                            <textarea id="message" class="form-control" rows="5" required></textarea>
                            <div class="error-message" id="messageError">Please enter your message</div>
                        </div>
                        <button type="submit" class="submit-btn">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter">
        <div class="container">
            <h3>Never miss an update, Subscribe now</h3>
            <form class="newsletter-form" action="newsletter.php" method="get">
                <input type="hidden" name="source" value="Homepage">
                <input type="email" name="email" class="newsletter-input" placeholder="Enter your email address" required>
                <button type="submit" class="newsletter-btn">Subscribe</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container footer-container">
            <div class="footer-col">
                <div class="logo">
                    <img src="https://natcodev.com.ng/images/logo.jpg" alt="Natcodev Logo">
                    <span class="logo-text">NATCODEV</span>
                </div>
                <p style="margin-top: 20px; color: #bdc3c7;">Address: Suite 7 11, 3 Floor, Falcon Mall, 24/25, Nelson Mandela way, Wuse Zone 4, Abuja</p>
                <p style="color: #bdc3c7;">Tel: 08154087918, 07060866485, 09110844132</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="/">Home</a></li>
                    <li><a href="#registration">Farmers</a></li>
                    <li><a href="https://investors.natcodev.com.ng">Investors</a></li>
                    <li><a href="#registration">Service Providers</a></li>
                    <li><a href="#platform-access">Platform Portals</a></li>
                    <li><a href="recruitment.php">Recruitment</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                    <li><a href="dashboard/login.php">Grower Dashboard</a></li>
                    <li><a href="admin/admin.php">Admin Portal</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Resources</h4>
                <ul class="footer-links">
                    <li><a href="apply.php?type=farmer">Coconut Registry</a></li>
                    <li><a href="apply.php?type=outgrower">Coconut Commercial Growers</a></li>
                    <li><a href="apply.php?type=cooperative">Coconut Farmers Co-operative</a></li>
                    <li><a href="mobile/">NATCODEV E-REGISTRY</a></li>
                    <li><a href="verify-certificate.php">Certificate Verification</a></li>
                    <li><a href="recruitment.php">Field Network Recruitment</a></li>
                    <li><a href="admin/production-readiness.php">Production Readiness</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <ul class="footer-links">
                    <li><a href="/privacy.html">Privacy Policy</a></li>
                    <li><a href="/terms.html">Terms of Service</a></li>
                    <li><a href="/cookie-policy.html">Cookie Policy</a></li>
                    <li><a href="/disclaimer.html">Disclaimer</a></li>
                    <li><a href="/accessibility.html">Accessibility</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">
            ©2023 NATCODEV All Rights Reserved
        </div>
    </footer>

    <!-- Modal for registration success -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 class="modal-title">Registration Successful!</h3>
            <div class="modal-body">
                <p>Thank you for registering with NATCODEV. We've sent a confirmation email to your inbox. Please check your email and follow the instructions to complete your registration.</p>
                <p>Our team will review your application and get back to you within 2-3 business days.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" id="closeModalBtn">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.loading-spinner').style.display = 'none';

        const animateElements = document.querySelectorAll('.animate-on-scroll');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.1
        });

        animateElements.forEach(element => {
            observer.observe(element);
        });

        // Hero Carousel Functionality
        const slides = document.querySelectorAll('.slide');
        const controls = document.querySelectorAll('.hero-control');
        const prevBtn = document.querySelector('.prev-slide');
        const nextBtn = document.querySelector('.next-slide');
        let currentSlide = 0;
        let slideInterval;

        function showSlide(index) {
            // Remove active class from all slides and controls
            slides.forEach(slide => slide.classList.remove('active'));
            controls.forEach(control => control.classList.remove('active'));
            
            // Add active class to current slide and control
            slides[index].classList.add('active');
            controls[index].classList.add('active');
            currentSlide = index;
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }

        function prevSlide() {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(currentSlide);
        }

        // Set up automatic sliding
        function startSlideShow() {
            slideInterval = setInterval(nextSlide, 5000);
        }

        function stopSlideShow() {
            clearInterval(slideInterval);
        }

        // Event listeners for controls
        controls.forEach((control, index) => {
            control.addEventListener('click', () => {
                showSlide(index);
                stopSlideShow();
                startSlideShow(); // Restart timer after manual change
            });
        });

        prevBtn.addEventListener('click', () => {
            prevSlide();
            stopSlideShow();
            startSlideShow();
        });

        nextBtn.addEventListener('click', () => {
            nextSlide();
            stopSlideShow();
            startSlideShow();
        });

        // Pause slideshow on hover
        const heroSection = document.querySelector('.hero');
        heroSection.addEventListener('mouseenter', stopSlideShow);
        heroSection.addEventListener('mouseleave', startSlideShow);

        // Start the slideshow
        startSlideShow();

        // Navigation active state
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('nav a').forEach(navLink => {
                    navLink.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        // Form validation
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            let isValid = true;
            
            // Reset error messages
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.form-control').forEach(el => el.classList.remove('invalid'));
            
            // Validate first name
            const firstName = document.getElementById('firstName');
            if (!firstName.value.trim()) {
                showError(firstName, 'firstNameError');
                isValid = false;
            }
            
            // Validate last name
            const lastName = document.getElementById('lastName');
            if (!lastName.value.trim()) {
                showError(lastName, 'lastNameError');
                isValid = false;
            }
            
            // Validate email
            const email = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailRegex.test(email.value)) {
                showError(email, 'emailError');
                isValid = false;
            }
            
            // Validate message
            const message = document.getElementById('message');
            if (!message.value.trim()) {
                showError(message, 'messageError');
                isValid = false;
            }
            
            if (isValid) {
                // Show success modal
                document.getElementById('successModal').style.display = 'flex';
                
                // Reset form
                this.reset();
            }
        });
        
        function showError(inputElement, errorId) {
            inputElement.classList.add('invalid');
            document.getElementById(errorId).style.display = 'block';
        }

        // Close modal
        document.getElementById('closeModalBtn').addEventListener('click', function() {
            document.getElementById('successModal').style.display = 'none';
        });
        
        document.querySelector('.close-modal').addEventListener('click', function() {
            document.getElementById('successModal').style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('successModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Newsletter subscription
        document.querySelector('.newsletter-form').addEventListener('submit', function() {
            // Submit to newsletter.php so the homepage opt-in reaches the existing system.
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (!href || href === '#') {
                    return;
                }
                const target = document.querySelector(href);
                if (!target) {
                    return;
                }
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Login button functionality
        document.querySelector('.login-btn').addEventListener('click', function() {
            window.location.href = this.getAttribute('href');
        });

        // Add hover effect to partner logos
        document.querySelectorAll('.partner-logo').forEach(logo => {
            logo.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            logo.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>
