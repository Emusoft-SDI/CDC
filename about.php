<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NATCODEV - Your Gateway to Innovation in Coconut Farming</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/forms.js"></script>
    <?php require 'about-partials/style.php'; ?>
</head>
<body>
    <div class="loading-spinner">
        <div class="spinner"></div>
    </div>

    <!-- Header Section -->
    <header>
        <div class="container header-container">
            <div class="logo">
                <img src="https://natcodev.com.ng/images/logo.jpg" alt="Natcodev Logo">
                <span class="logo-text">NATCODEV</span>
            </div>
            <nav>
                <ul>
                    <li><a href="/" class="active">Home</a></li>
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
    <?php require 'about-partials/hero.php'; ?>

    <!-- Partners Section -->
    <?php require 'about-partials/partners.php'; ?>

    <!-- Partnership Section -->
    <?php require 'about-partials/partnership.php'; ?>

    <!-- Registration Section -->
    <?php require 'about-partials/registration.php'; ?>

    <?php require 'about-partials/platform-access.php'; ?>

    <!-- Contact Section -->
    <?php require 'about-partials/contact.php'; ?>

    <!-- Newsletter Section -->
    <?php require 'about-partials/newsletter.php'; ?>

    <!-- Footer -->
    <?php require 'about-partials/footer.php'; ?>

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
