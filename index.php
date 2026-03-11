<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FeedbackIQ - Customer Feedback Software</title>
    <link rel="stylesheet" href="public/css/style.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="nav-logo">
            <!-- Replace src with your logo path -->
            <img src="public/images/logo.jpg" alt="FeedbackIQ" class="logo-img">
        </a>
        <div class="nav-links" id="navLinks">
            <a href="#features">Features</a>
            <a href="#how-it-works">Process</a>
            <a href="#industries">Industries</a>
            <a href="public/login.php" class="btn-secondary">Sign In</a>
            <a href="public/register.php" class="btn-primary">Get Started</a>
        </div>
    </nav>

    <header class="hero">
        <span class="hero-tag">Empower Your Customer Experience</span>
        <h1>Drive better growth with real-time feedback</h1>
        <p>The champion of customer service. Collect insights via QR codes and transform them into actionable intelligence with our prescriptive engine.</p>
        <div class="hero-btns">
            <a href="public/register.php" class="btn-primary" style="padding: 1rem 2.5rem; font-size: 1.125rem;">Start Free Trial</a>
            <a href="#features" class="btn-secondary" style="padding: 1rem 2.5rem; font-size: 1.125rem;">View Demo</a>
        </div>
    </header>

    <section id="features" class="section">
        <div class="section-title">
            <h2>Everything you need to deliver faster resolutions</h2>
            <p>Advanced tools for modern businesses to capture, analyze, and act on customer sentiment.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="qr-code"></i></div>
                <h3>QR Feedback</h3>
                <p>Seamlessly collect feedback at any touchpoint. No apps or logins required for customers—just a simple scan.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="bar-chart-3"></i></div>
                <h3>Descriptive Analytics</h3>
                <p>Visualize trends and satisfaction scores through a high-definition dashboard. Understand exactly what's happening.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="lightbulb"></i></div>
                <h3>Prescriptive Insights</h3>
                <p>Get data-driven recommendations. Our engine applies rule-based logic to suggest improvements for your service.</p>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="section section-muted">
        <div class="section-content">
            <div class="section-title">
                <h2>A simple path to excellence</h2>
                <p>Set up your platform and start improving your service in minutes.</p>
            </div>
            <div class="process-steps">
                <div class="step">
                    <div class="step-number">01</div>
                    <h4>Register</h4>
                    <p>Onboard your business and define your service categories.</p>
                </div>
                <div class="step">
                    <div class="step-number">02</div>
                    <h4>Generate</h4>
                    <p>Instantly create high-resolution QR codes for your locations.</p>
                </div>
                <div class="step">
                    <div class="step-number">03</div>
                    <h4>Collect</h4>
                    <p>Customers provide feedback via mobile-friendly forms.</p>
                </div>
                <div class="step">
                    <div class="step-number">04</div>
                    <h4>Improve</h4>
                    <p>Apply automated recommendations to optimize your service.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="industries" class="section">
        <div class="section-title">
            <h2>Trusted across every industry</h2>
            <p>From retail to healthcare, FeedbackIQ adapts to your specific business model.</p>
        </div>
        <div class="industries-grid">
            <div class="industry-item">Retail</div>
            <div class="industry-item">Food & Beverage</div>
            <div class="industry-item">Healthcare</div>
            <div class="industry-item">Technology</div>
            <div class="industry-item">E-commerce</div>
            <div class="industry-item">Institutions</div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <!-- Replace src with your light/footer logo path -->
                <img src="public/images/logo.jpg" alt="FeedbackIQ" class="footer-logo">
                <p>Providing businesses with practical, scalable analytics tools to bridge the gap between feedback and excellence.</p>
            </div>
            <div class="footer-links">
                <h4>Solutions</h4>
                <ul>
                    <li><a href="public/login.php">Sign In</a></li>
                    <li><a href="public/register.php">Registration</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">Process</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Company</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Feedback Analysis Platform. Empowering businesses through data.</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
        
        // Smooth scroll to top when clicking logo
        const logoLink = document.querySelector('.nav-logo');
        if (logoLink) {
            logoLink.addEventListener('click', function (e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    left: 0,
                    behavior: 'smooth'
                });
            });
        }
    </script>
</body>
</html>
