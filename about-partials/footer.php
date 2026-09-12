<?php
require_once __DIR__ . '/../config.php';
?>
    <footer>
        <div class="container footer-container">
            <div class="footer-col">
                <div class="logo">
                    <img src="https://natcodev.com.ng/images/logo.jpg" alt="Natcodev Logo">
                    <span class="logo-text">NATCODEV</span>
                </div>
                <?php $office = app_contact_office(); ?>
                <div style="margin-top: 12px; color: #bdc3c7; line-height: 1.45; font-size: .95rem; max-width: 340px;">
                    <strong style="color:#fff;display:block;margin-bottom:4px;">Address</strong>
                    <span style="display:block; margin-bottom: 10px;"><?= e(implode(', ', $office['address_lines'])) ?></span>
                    <strong style="color:#fff;display:block;margin-bottom:4px;">Call Us</strong>
                    <a href="tel:<?= e($office['phone_tel']) ?>" style="color:#fff;"><?= e($office['phone_display']) ?></a>
                </div>
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
