<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare General Hospital — Excellence in Healthcare</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="/Web/Hospital-Management-System/includes/layout.css">
    <link rel="stylesheet" href="/Web/Hospital-Management-System/includes/home.css">
    
</head>
<body class="public-site">

<!-- ═══════════════════════════════════════════
     NAV BAR
════════════════════════════════════════════ -->
<nav class="pub-nav" id="mainNav">
    <div class="pub-nav-inner">
        <a href="#" class="pub-nav-logo">
            <div class="pub-logo-mark">M</div>
            <div>
                <span class="pub-logo-name">MediCare</span>
                <span class="pub-logo-sub">General Hospital</span>
            </div>
        </a>

        <ul class="pub-nav-links">
            <li><a href="#about">About</a></li>
            <li><a href="#departments">Departments</a></li>
            <li><a href="#doctors">Doctors</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="pub-nav-actions">
            <a href="emergency.php" class="emergency-nav-btn">
                <span class="emergency-pulse-nav"></span>
                Emergency
            </a>
            <a href="/Web/Hospital-Management-System/login.php" class="btn-nav-outline">Staff Login</a>
            <a href="/Web/Hospital-Management-System/login.php" class="btn-nav-solid">Patient Portal</a>
        </div>
    </div>
</nav>
<!-- Emergency Button Near Staff Login -->
<!-- Emergency Button Near Staff Login -->
<style>
    .pub-nav-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .emergency-nav-btn {
        background: #ff2d2d;
        color: white;
        padding: 10px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(255, 45, 45, 0.3);
        position: relative;
        overflow: hidden;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        z-index: 1;
        animation: emergencyGlow 2s infinite;
    }

    .emergency-nav-btn:hover {
        background: #d90000;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 45, 45, 0.5);
    }

    .emergency-nav-btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 10px rgba(255, 45, 45, 0.3);
    }

    .emergency-pulse-nav {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: white;
        border-radius: 50%;
        animation: pulseDot 1.5s infinite;
    }

    @keyframes emergencyGlow {
        0%, 100% {
            box-shadow: 0 4px 15px rgba(255, 45, 45, 0.3);
        }
        50% {
            box-shadow: 0 4px 25px rgba(255, 45, 45, 0.6);
        }
    }

    @keyframes pulseDot {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.5;
            transform: scale(1.5);
        }
    }

    /* Responsive adjustments */
    @media (max-width: 1024px) {
        .pub-nav-actions {
            gap: 8px;
        }
        
        .emergency-nav-btn {
            padding: 8px 16px;
            font-size: 13px;
        }
    }
</style>
<!-- ═══════════════════════════════════════════
     HERO
════════════════════════════════════════════ -->
<section class="hero">
    <div class="hero-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <div class="hero-inner">
        <div class="hero-text">
            <div class="hero-badge"><i class="fas fa-hospital professional-icon"></i> Accredited Hospital — Est. 2008</div>
            <h1 class="hero-title display-font">
                Your Health,<br>Our <em>Priority</em>
            </h1>
            <p class="hero-sub">
                MediCare General Hospital delivers world-class medical care with compassion.
                From routine check-ups to advanced surgery — we're here for every step of your journey.
            </p>
            <div class="hero-cta">
                <a href="/Web/Hospital-Management-System/register_patient.php" class="btn btn-primary btn-lg">Book an Appointment</a>
                <a href="#departments" class="btn btn-hero-ghost btn-lg">Our Departments <i class="fas fa-arrow-down" style="margin-left:6px;"></i></a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num">25k+</span>
                    <span class="hero-stat-label">Patients Served</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <span class="hero-stat-num">120+</span>
                    <span class="hero-stat-label">Specialist Doctors</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <span class="hero-stat-num">15+</span>
                    <span class="hero-stat-label">Years of Excellence</span>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-card-main">
                <div class="hcard-header">
                    <div class="hcard-dot green"></div>
                    <span>Appointments Today</span>
                </div>
                <div class="hcard-big">148</div>
                <div class="hcard-sub"><i class="fas fa-arrow-trend-up"></i> 12% from yesterday</div>
            </div>
            <div class="hero-card-secondary top-right">
                <div class="hcard2-icon"><i class="fas fa-stethoscope"></i></div>
                <div>
                    <div class="hcard2-label">Doctors On Duty</div>
                    <div class="hcard2-val">38</div>
                </div>
            </div>
            <div class="hero-card-secondary bottom-left">
                <div class="hcard2-icon"><i class="fas fa-bed"></i></div>
                <div>
                    <div class="hcard2-label">Beds Available</div>
                    <div class="hcard2-val">64 / 120</div>
                </div>
            </div>
            <div class="hero-card-secondary bottom-right">
                <div class="hcard2-icon"><i class="fas fa-truck-medical"></i></div>
                <div>
                    <div class="hcard2-label">Ambulances Ready</div>
                    <div class="hcard2-val">8</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency bar -->
    <div class="emergency-bar">
        <div class="emergency-bar-inner">
            <span class="emergency-pulse"></span>
            <strong>24/7 Emergency Services</strong>
            <span>•</span>
            <span>Call Ambulance: <strong>+94 11 234 5678</strong></span>
            <span>•</span>
            <span>Emergency Ward: <strong>Ground Floor, Block A</strong></span>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     QUICK ACCESS PORTALS
════════════════════════════════════════════ -->
<section class="portals-section">
    <div class="container">
        <div class="portals-grid">
            <a href="/Web/Hospital-Management-System/register_patient.php" class="portal-card portal-patient">
                <div class="portal-icon"><i class="fas fa-user"></i></div>
                <h3>Patient Portal</h3>
                <p>Register, book appointments, view your medical history and bills online.</p>
                <span class="portal-link">Get Started <i class="fas fa-arrow-right"></i></span>
            </a>
            <a href="/Web/Hospital-Management-System/login.php" class="portal-card portal-staff">
                <div class="portal-icon"><i class="fas fa-hospital-user"></i></div>
                <h3>Staff Portal</h3>
                <p>Access the Hospital Management System for clinical and administrative work.</p>
                <span class="portal-link">Staff Login <i class="fas fa-arrow-right"></i></span>
            </a>
            <a href="tel:+94112345678" class="portal-card portal-emergency">
                <div class="portal-icon"><i class="fas fa-phone-volume"></i></div>
                <h3>Emergency</h3>
                <p>Life-threatening emergency? Call us immediately or request an ambulance online.</p>
                <span class="portal-link">Call Now <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     ABOUT
════════════════════════════════════════════ -->
<section class="about-section" id="about">
    <div class="container">
        <div class="about-grid">
            <div class="about-img-col">
                <div class="about-img-block">
                    <div class="about-img-badge">
                        <span class="about-img-badge-num">15+</span>
                        <span>Years of<br>Excellence</span>
                    </div>
                    <!-- Decorative hospital illustration -->
                    <div class="about-illustration">
                        <div class="illus-building">
                            <div class="illus-cross"><i class="fas fa-plus"></i></div>
                            <div class="illus-windows">
                                <span></span><span></span><span></span>
                                <span></span><span></span><span></span>
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="about-text-col">
                <div class="section-badge">About MediCare</div>
                <h2 class="display-font">Trusted Care for Every<br>Stage of Life</h2>
                <p class="about-lead">
                    Since 2008, MediCare General Hospital has been at the forefront of healthcare delivery in the region. Our integrated, patient-centred approach combines cutting-edge technology with genuine compassion.
                </p>
                <div class="about-points">
                    <div class="about-point">
                        <div class="about-point-icon"><i class="fas fa-user-graduate"></i></div>
                        <div>
                            <strong>Expert Medical Team</strong>
                            <p>Over 120 specialists across 14 departments with local and international training.</p>
                        </div>
                    </div>
                    <div class="about-point">
                        <div class="about-point-icon"><i class="fas fa-microscope"></i></div>
                        <div>
                            <strong>Advanced Technology</strong>
                            <p>State-of-the-art diagnostic imaging, fully equipped operation theatres, and modern ICU.</p>
                        </div>
                    </div>
                    <div class="about-point">
                        <div class="about-point-icon"><i class="fas fa-heart"></i></div>
                        <div>
                            <strong>Patient-Centred Care</strong>
                            <p>Every care plan is tailored to the individual. Your comfort and dignity always come first.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     DEPARTMENTS
════════════════════════════════════════════ -->
<section class="depts-section" id="departments">
    <div class="container">
        <div class="section-header text-center">
            <div class="section-badge">Our Departments</div>
            <h2 class="display-font">Comprehensive Medical Specialties</h2>
            <p class="section-sub">From general medicine to advanced surgery, our departments cover every aspect of your health.</p>
        </div>
        <div class="depts-grid">
            <?php
            $departments = [
                ['icon'=>'fa-solid fa-heart-pulse',  'name'=>'Cardiology',         'desc'=>'Heart disease diagnosis, treatment, and preventive care.'],
                ['icon'=>'fa-solid fa-brain',        'name'=>'Neurology',           'desc'=>'Brain, spine, and nervous system disorders.'],
                ['icon'=>'fa-solid fa-bone',         'name'=>'Orthopaedics',        'desc'=>'Bone, joint, and musculoskeletal surgery.'],
                ['icon'=>'fa-solid fa-child',        'name'=>'Paediatrics',         'desc'=>'Specialised care for infants, children, and adolescents.'],
                ['icon'=>'fa-solid fa-person-pregnant', 'name'=>'Obstetrics & Gynaecology', 'desc'=>'Maternity care, childbirth, and women\'s health.'],
                ['icon'=>'fa-solid fa-eye',          'name'=>'Ophthalmology',        'desc'=>'Eye care, vision correction, and retinal treatment.'],
                ['icon'=>'fa-solid fa-tooth',        'name'=>'Dental',              'desc'=>'General and specialist dental procedures.'],
                ['icon'=>'fa-solid fa-x-ray',        'name'=>'Radiology',           'desc'=>'MRI, CT scans, X-rays, and ultrasound imaging.'],
                ['icon'=>'fa-solid fa-capsules',     'name'=>'Pharmacy',            'desc'=>'In-hospital dispensary with full prescription services.'],
                ['icon'=>'fa-solid fa-flask',        'name'=>'Laboratory',          'desc'=>'Blood, urine, and pathology testing services.'],
                ['icon'=>'fa-solid fa-truck-medical','name'=>'Emergency',           'desc'=>'24/7 critical care and trauma response.'],
                ['icon'=>'fa-solid fa-scalpel',      'name'=>'General Surgery',     'desc'=>'Elective and emergency surgical procedures.'],
            ];
            foreach ($departments as $d): ?>
            <div class="dept-card">
                <div class="dept-icon"><i class="<?php echo $d['icon']; ?>"></i></div>
                <h4><?php echo htmlspecialchars($d['name']); ?></h4>
                <p><?php echo htmlspecialchars($d['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     DOCTORS
════════════════════════════════════════════ -->
<section class="doctors-section" id="doctors">
    <div class="container">
        <div class="section-header text-center">
            <div class="section-badge">Our Specialists</div>
            <h2 class="display-font">Meet Our Doctors</h2>
            <p class="section-sub">Highly qualified specialists dedicated to your health and well-being.</p>
        </div>
        <div class="doctors-grid">
            <?php
            $doctors = [
                ['name'=>'Dr. Amara Perera',    'spec'=>'Senior Cardiologist',         'exp'=>'18 Years',  'avail'=>'Mon – Fri'],
                ['name'=>'Dr. Nuwan Jayasekara','spec'=>'Consultant Neurologist',       'exp'=>'12 Years',  'avail'=>'Mon – Thu'],
                ['name'=>'Dr. Sachini Fernando', 'spec'=>'Obs. & Gynaecology',          'exp'=>'15 Years',  'avail'=>'Tue – Sat'],
                ['name'=>'Dr. Roshan Kumara',   'spec'=>'Orthopaedic Surgeon',          'exp'=>'10 Years',  'avail'=>'Mon / Wed / Fri'],
                ['name'=>'Dr. Dilini Weerasena','spec'=>'Consultant Paediatrician',     'exp'=>'14 Years',  'avail'=>'Mon – Sat'],
                ['name'=>'Dr. Thilak Bandara',  'spec'=>'General Surgeon',              'exp'=>'20 Years',  'avail'=>'Wed – Sun'],
            ];
            foreach ($doctors as $doc): ?>
            <div class="doctor-card">
                <div class="doctor-avatar"><?php echo strtoupper(substr($doc['name'], 4, 1)); ?></div>
                <div class="doctor-info">
                    <h4><?php echo htmlspecialchars($doc['name']); ?></h4>
                    <p class="doctor-spec"><?php echo htmlspecialchars($doc['spec']); ?></p>
                    <div class="doctor-meta">
                        <span><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($doc['exp']); ?></span>
                        <span><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars($doc['avail']); ?></span>
                    </div>
                </div>
                <a href="/Web/Hospital-Management-System/login.php" class="btn btn-sm btn-primary" style="margin-top:14px;width:100%;justify-content:center">Book Appointment</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     SERVICES / WHY US
════════════════════════════════════════════ -->
<section class="services-section" id="services">
    <div class="container">
        <div class="services-inner">
            <div class="services-left">
                <div class="section-badge">Why Choose Us</div>
                <h2 class="display-font">Healthcare You Can Trust</h2>
                <p style="color:rgba(255,255,255,0.75);margin:16px 0 32px;line-height:1.8">
                    We combine the latest medical technology with a warm, patient-first culture to deliver care you can count on.
                </p>
                <div class="services-list">
                    <?php
                    $services = [
                        ['fa-solid fa-clock', '24/7 Emergency Services', 'Round-the-clock emergency care with rapid response teams.'],
                        ['fa-solid fa-mobile-screen', 'Online Appointment Booking', 'Book, reschedule, or cancel appointments from any device.'],
                        ['fa-solid fa-prescription', 'In-House Pharmacy', 'Full prescription dispensing with electronic records.'],
                        ['fa-solid fa-truck-medical', 'Ambulance Dispatch', 'Modern fleet with GPS tracking for the fastest response.'],
                        ['fa-solid fa-vial-circle-check', 'Advanced Diagnostics', 'On-site lab, radiology, and pathology for same-day results.'],
                        ['fa-solid fa-credit-card', 'Flexible Billing', 'Transparent billing with advance and full payment options.'],
                    ];
                    foreach ($services as [$icon, $title, $desc]): ?>
                    <div class="service-item">
                        <div class="service-icon"><i class="<?php echo $icon; ?>"></i></div>
                        <div>
                            <strong><?php echo htmlspecialchars($title); ?></strong>
                            <p><?php echo htmlspecialchars($desc); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="services-right">
                <div class="services-stats-card">
                    <h3>Hospital at a Glance</h3>
                    <div class="glance-grid">
                        <div class="glance-item"><span class="glance-num">120</span><span class="glance-label">Beds</span></div>
                        <div class="glance-item"><span class="glance-num">14</span><span class="glance-label">Departments</span></div>
                        <div class="glance-item"><span class="glance-num">120+</span><span class="glance-label">Specialists</span></div>
                        <div class="glance-item"><span class="glance-num">8</span><span class="glance-label">Ambulances</span></div>
                        <div class="glance-item"><span class="glance-num">5</span><span class="glance-label">Operation Theatres</span></div>
                        <div class="glance-item"><span class="glance-num">24/7</span><span class="glance-label">Emergency</span></div>
                    </div>
                    <a href="/Web/Hospital-Management-System/register_patient.php" class="btn btn-primary btn-full btn-lg" style="margin-top:24px">
                        Register as Patient
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     CONTACT
════════════════════════════════════════════ -->
<section class="contact-section" id="contact">
    <div class="container">
        <div class="section-header text-center">
            <div class="section-badge">Get in Touch</div>
            <h2 class="display-font">Contact & Visiting Hours</h2>
        </div>
        <div class="contact-grid">
            <div class="contact-card">
                <div class="contact-icon"><i class="fas fa-location-dot"></i></div>
                <h4>Address</h4>
                <p>42 Medical Centre Road<br>Colombo 07, Sri Lanka</p>
            </div>
            <div class="contact-card">
                <div class="contact-icon"><i class="fas fa-phone"></i></div>
                <h4>Phone</h4>
                <p>Reception: +94 11 234 5678<br>Emergency: +94 11 234 5000</p>
            </div>
            <div class="contact-card">
                <div class="contact-icon"><i class="fas fa-clock"></i></div>
                <h4>OPD Hours</h4>
                <p>Weekdays: 8:00 AM – 8:00 PM<br>Weekends: 9:00 AM – 5:00 PM</p>
            </div>
            <div class="contact-card">
                <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                <h4>Email</h4>
                <p>info@medicare-hospital.lk<br>appointments@medicare-hospital.lk</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     FOOTER
════════════════════════════════════════════ -->
<footer class="pub-footer">
    <div class="container">
        <div class="pub-footer-top">
            <div class="pub-footer-brand">
                <div class="pub-footer-logo">M</div>
                <div>
                    <div class="pub-footer-name">MediCare General Hospital</div>
                    <div class="pub-footer-tagline">Excellence in Healthcare</div>
                </div>
            </div>
            <div class="pub-footer-links">
                <h5>Quick Links</h5>
                <a href="#about">About Us</a>
                <a href="#departments">Departments</a>
                <a href="#doctors">Our Doctors</a>
                <a href="#contact">Contact</a>
            </div>
            <div class="pub-footer-links">
                <h5>Patient Services</h5>
                <a href="/Web/Hospital-Management-System/register_patient.php">Register as Patient</a>
                <a href="/Web/Hospital-Management-System/login.php">Patient Portal Login</a>
                <a href="/Web/Hospital-Management-System/login.php">Book Appointment</a>
            </div>
            <div class="pub-footer-links">
                <h5>Staff Access</h5>
                <a href="/Web/Hospital-Management-System/login.php">Staff Login</a>
                <a href="/Web/Hospital-Management-System/register_staff.php">Staff Registration</a>
            </div>
        </div>
        <div class="pub-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> MediCare General Hospital. All rights reserved.</span>
            <span>ICT1242 Web Development Practicum — Group 05</span>
        </div>
    </div>
</footer>

<script>
// Sticky nav on scroll
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 60);
});

// Image slider (hero cards subtle animation)
const cards = document.querySelectorAll('.hero-card-secondary');
let i = 0;
setInterval(() => {
    cards.forEach(c => c.classList.remove('pulse'));
    cards[i % cards.length].classList.add('pulse');
    i++;
}, 2000);
</script>

</body>
</html>