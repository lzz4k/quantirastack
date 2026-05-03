// ===== QUANTIRA STACK - Main JavaScript =====

document.addEventListener('DOMContentLoaded', function () {

    // --- Navbar scroll effect ---
    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // --- Mobile nav toggle ---
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function () {
            navLinks.classList.toggle('active');
            const icon = navToggle.querySelector('i');
            if (navLinks.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-xmark');
            } else {
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-bars');
            }
        });

        // Close mobile nav on link click
        navLinks.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                navLinks.classList.remove('active');
                const icon = navToggle.querySelector('i');
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-bars');
            });
        });
    }

    // --- Scroll animations (Intersection Observer) ---
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -60px 0px',
        threshold: 0.1
    };

    const animateObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                animateObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll(
        '.service-card, .solution-card, .about-card, .stat-item'
    ).forEach(function (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        animateObserver.observe(el);
    });

    // --- Smooth scroll for anchor links ---
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // --- Contact form AJAX submission ---
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const name = document.getElementById('full_name');
            const email = document.getElementById('email');
            const subject = document.getElementById('subject');
            const message = document.getElementById('message');
            const submitBtn = contactForm.querySelector('button[type="submit"]');

            let valid = true;

            [name, email, subject, message].forEach(function (field) {
                if (field) field.style.borderColor = '';
            });

            if (name && name.value.trim() === '') { name.style.borderColor = '#f43f5e'; valid = false; }
            if (email && email.value.trim() === '') { email.style.borderColor = '#f43f5e'; valid = false; }
            else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { email.style.borderColor = '#f43f5e'; valid = false; }
            if (subject && subject.value === '') { subject.style.borderColor = '#f43f5e'; valid = false; }
            if (message && message.value.trim() === '') { message.style.borderColor = '#f43f5e'; valid = false; }

            if (!valid) return;

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Sending...</span>';

            var formData = new FormData(contactForm);

            fetch('submit.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                // Remove any existing alerts
                var existing = contactForm.closest('.contact-form-wrapper').querySelector('.alert');
                if (existing) existing.remove();

                var alertDiv = document.createElement('div');
                alertDiv.className = 'alert ' + (data.success ? 'alert-success' : 'alert-error');
                alertDiv.innerHTML = '<i class="fas ' + (data.success ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i><span>' + data.message + '</span>';
                contactForm.closest('.contact-form-wrapper').prepend(alertDiv);

                if (data.success) {
                    contactForm.reset();
                    setTimeout(function() {
                        alertDiv.style.transition = 'opacity 0.5s ease';
                        alertDiv.style.opacity = '0';
                        setTimeout(function() { alertDiv.remove(); }, 500);
                    }, 5000);
                }

                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Send Message</span> <i class="fas fa-paper-plane"></i>';
            })
            .catch(function() {
                var alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Something went wrong. Please try again.</span>';
                contactForm.closest('.contact-form-wrapper').prepend(alertDiv);

                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Send Message</span> <i class="fas fa-paper-plane"></i>';
            });
        });

        // Clear error styling on input
        contactForm.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.addEventListener('input', function () { this.style.borderColor = ''; });
            field.addEventListener('change', function () { this.style.borderColor = ''; });
        });
    }

    // --- Auto-dismiss alerts after 5 seconds ---
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function () {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });

    // --- Mouse parallax on hero orbs ---
    var heroSection = document.querySelector('.hero');
    if (heroSection) {
        var orbs = heroSection.querySelectorAll('.hero-orb');
        heroSection.addEventListener('mousemove', function (e) {
            var rect = heroSection.getBoundingClientRect();
            var x = (e.clientX - rect.left) / rect.width - 0.5;
            var y = (e.clientY - rect.top) / rect.height - 0.5;
            orbs.forEach(function (orb, i) {
                var factor = (i + 1) * 25;
                orb.style.transform = 'translate(' + (x * factor) + 'px, ' + (y * factor) + 'px)';
            });
        });
    }

    // --- Tilt effect on cards ---
    document.querySelectorAll('.service-card, .solution-card, .about-card').forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
            var rect = card.getBoundingClientRect();
            var x = (e.clientX - rect.left) / rect.width;
            var y = (e.clientY - rect.top) / rect.height;
            var rotateX = (y - 0.5) * -8;
            var rotateY = (x - 0.5) * 8;
            card.style.transform = 'perspective(800px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-8px) scale(1.02)';
        });
        card.addEventListener('mouseleave', function () {
            card.style.transform = '';
        });
    });

});
