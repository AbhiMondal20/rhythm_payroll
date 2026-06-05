<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Support';

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* =========================================
   SUPPORT PAGE
========================================= */

.support-wrapper{
    display:grid;
    grid-template-columns: 320px 1fr;
    gap:24px;
    margin-top:20px;
}

.support-sidebar,
.support-content{
    background:#fff;
    border:1px solid #E5E7EB;
    border-radius:16px;
    overflow:hidden;
}

/* Sidebar */
.support-sidebar-head{
    padding:22px;
    border-bottom:1px solid #F3F4F6;
}

.support-sidebar-head h2{
    font-size:20px;
    font-weight:700;
    color:#111827;
    margin-bottom:6px;
}

.support-sidebar-head p{
    font-size:13px;
    color:#6B7280;
    line-height:1.5;
}

.support-menu{
    padding:12px;
}

.support-menu a{
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px 16px;
    border-radius:12px;
    text-decoration:none;
    color:#374151;
    font-size:14px;
    font-weight:500;
    transition:.2s;
    margin-bottom:6px;
}

.support-menu a:hover{
    background:#F3F4F6;
}

.support-menu a.active{
    background:#2563EB;
    color:#fff;
}

.support-menu a svg{
    width:18px;
    height:18px;
    stroke:currentColor;
    fill:none;
    stroke-width:2;
}

/* Content */
.support-content-head{
    padding:24px 28px;
    border-bottom:1px solid #F3F4F6;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
}

.support-content-head h1{
    font-size:24px;
    font-weight:700;
    color:#111827;
    margin:0;
}

.support-content-head p{
    font-size:13px;
    color:#6B7280;
    margin-top:4px;
}

/* Cards */
.support-grid{
    padding:24px;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:18px;
}

.support-card{
    border:1px solid #E5E7EB;
    border-radius:14px;
    padding:20px;
    transition:.2s;
    background:#fff;
}

.support-card:hover{
    border-color:#2563EB;
    transform:translateY(-2px);
    box-shadow:0 10px 25px rgba(37,99,235,.08);
}

.support-icon{
    width:52px;
    height:52px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#EFF6FF;
    margin-bottom:16px;
}

.support-icon svg{
    width:24px;
    height:24px;
    stroke:#2563EB;
    fill:none;
    stroke-width:2;
}

.support-card h3{
    font-size:16px;
    font-weight:700;
    color:#111827;
    margin-bottom:8px;
}

.support-card p{
    font-size:13px;
    color:#6B7280;
    line-height:1.6;
    margin-bottom:16px;
}

.support-btn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:10px 16px;
    background:#2563EB;
    color:#fff;
    text-decoration:none;
    border-radius:10px;
    font-size:13px;
    font-weight:600;
    transition:.2s;
}

.support-btn:hover{
    background:#1D4ED8;
}

/* FAQ */
.support-faq{
    padding:0 24px 24px;
}

.support-faq h2{
    font-size:18px;
    font-weight:700;
    color:#111827;
    margin-bottom:18px;
}

.faq-item{
    border:1px solid #E5E7EB;
    border-radius:12px;
    margin-bottom:12px;
    overflow:hidden;
}

.faq-question{
    width:100%;
    border:none;
    background:#fff;
    padding:16px 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    color:#111827;
    font-family:inherit;
}

.faq-question:hover{
    background:#F9FAFB;
}

.faq-answer{
    display:none;
    padding:0 18px 18px;
    font-size:13px;
    color:#6B7280;
    line-height:1.7;
}

.faq-item.active .faq-answer{
    display:block;
}

/* Contact */
.support-contact{
    padding:24px;
    border-top:1px solid #F3F4F6;
    background:#FAFBFC;
}

.support-contact-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
}

.support-contact-card{
    background:#fff;
    border:1px solid #E5E7EB;
    border-radius:14px;
    padding:18px;
}

.support-contact-card h4{
    font-size:14px;
    font-weight:700;
    color:#111827;
    margin-bottom:8px;
}

.support-contact-card p,
.support-contact-card a{
    font-size:13px;
    color:#6B7280;
    text-decoration:none;
    line-height:1.6;
}

/* Toast */
.support-toast{
    position:fixed;
    right:24px;
    bottom:24px;
    background:#111827;
    color:#fff;
    padding:14px 18px;
    border-radius:12px;
    display:flex;
    align-items:center;
    gap:10px;
    font-size:13px;
    font-weight:500;
    box-shadow:0 10px 25px rgba(0,0,0,.18);
    transform:translateY(120px);
    opacity:0;
    transition:.3s;
    z-index:9999;
}

.support-toast.show{
    transform:translateY(0);
    opacity:1;
}

/* Responsive */
@media(max-width:991px){
    .support-wrapper{
        grid-template-columns:1fr;
    }
}

@media(max-width:640px){
    .support-content-head{
        padding:18px;
    }

    .support-grid,
    .support-faq,
    .support-contact{
        padding:18px;
    }
}
</style>

<div class="page-header">
    <h1 class="page-title">Support Center</h1>
</div>

<div class="support-wrapper">

    <!-- SIDEBAR -->
    <div class="support-sidebar">

        <div class="support-sidebar-head">
            <h2>Need Help?</h2>
            <p>
                Find answers, contact support, and explore guides for your payroll system.
            </p>
        </div>

        <div class="support-menu">

            <a href="#" class="active">
                <svg viewBox="0 0 24 24">
                    <path d="M3 12l9-9 9 9"></path>
                    <path d="M9 21V9h6v12"></path>
                </svg>
                Dashboard
            </a>

            <a href="#">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4"></path>
                    <path d="M12 8h.01"></path>
                </svg>
                Help Articles
            </a>

            <a href="#">
                <svg viewBox="0 0 24 24">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Live Chat
            </a>

            <a href="#">
                <svg viewBox="0 0 24 24">
                    <path d="M22 16.92V19a2 2 0 0 1-2.18 2"></path>
                    <path d="M2 5a2 2 0 0 1 2-2h3.28"></path>
                    <path d="M22 8.72V5a2 2 0 0 0-2-2h-3.28"></path>
                    <path d="M2 15.28V19a2 2 0 0 0 2 2h3.28"></path>
                </svg>
                Contact Support
            </a>

        </div>

    </div>

    <!-- CONTENT -->
    <div class="support-content">

        <div class="support-content-head">
            <div>
                <h1>Welcome to Support</h1>
                <p>
                    Get assistance for payroll, attendance, leave, and employee management.
                </p>
            </div>

            <button class="support-btn" onclick="showToast()">
                Contact Support
            </button>
        </div>

        <!-- Cards -->
        <div class="support-grid">

            <div class="support-card">
                <div class="support-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>

                <h3>Live Chat</h3>

                <p>
                    Connect instantly with our support team for quick issue resolution.
                </p>

                <a href="#" class="support-btn">
                    Start Chat
                </a>
            </div>

            <div class="support-card">
                <div class="support-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 4h16v16H4z"></path>
                        <path d="M4 9h16"></path>
                    </svg>
                </div>

                <h3>Knowledge Base</h3>

                <p>
                    Explore setup guides, FAQs, payroll tutorials, and documentation.
                </p>

                <a href="#" class="support-btn">
                    View Articles
                </a>
            </div>

            <div class="support-card">
                <div class="support-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M22 16.92V19a2 2 0 0 1-2.18 2"></path>
                        <path d="M2 5a2 2 0 0 1 2-2h3.28"></path>
                    </svg>
                </div>

                <h3>Call Support</h3>

                <p>
                    Speak directly with our technical support team during business hours.
                </p>

                <a href="#" class="support-btn">
                    Call Now
                </a>
            </div>

            <div class="support-card">
                <div class="support-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 20h9"></path>
                        <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"></path>
                    </svg>
                </div>

                <h3>Raise Ticket</h3>

                <p>
                    Create a support ticket and track issue progress in real time.
                </p>

                <a href="#" class="support-btn">
                    Create Ticket
                </a>
            </div>

        </div>

        <!-- FAQ -->
        <div class="support-faq">

            <h2>Frequently Asked Questions</h2>

            <div class="faq-item">
                <button class="faq-question">
                    How do I generate salary slips?
                    <span>+</span>
                </button>

                <div class="faq-answer">
                    Go to Payroll → Salary Process → Generate Payslip and select the required month.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question">
                    How can I reset employee attendance?
                    <span>+</span>
                </button>

                <div class="faq-answer">
                    Open Attendance Module and use the attendance correction option.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question">
                    How do I add leave policies?
                    <span>+</span>
                </button>

                <div class="faq-answer">
                    Navigate to Configuration → Leave → Leave Policies to create or manage leave rules.
                </div>
            </div>

        </div>

        <!-- Contact -->
        <div class="support-contact">

            <div class="support-contact-grid">

                <div class="support-contact-card">
                    <h4>Email Support</h4>
                    <p>support@company.com</p>
                </div>

                <div class="support-contact-card">
                    <h4>Phone Support</h4>
                    <p>+91 9876543210</p>
                </div>

                <div class="support-contact-card">
                    <h4>Working Hours</h4>
                    <p>Monday - Saturday<br>9:00 AM - 6:00 PM</p>
                </div>

            </div>

        </div>

    </div>

</div>

<!-- Toast -->
<div class="support-toast" id="supportToast">
    ✅ Support team will contact you shortly.
</div>

<script>
/* FAQ Toggle */
document.querySelectorAll('.faq-question').forEach(function(btn){

    btn.addEventListener('click', function(){

        let item = this.parentElement;

        document.querySelectorAll('.faq-item').forEach(function(el){
            if(el !== item){
                el.classList.remove('active');
                el.querySelector('span').innerText = '+';
            }
        });

        item.classList.toggle('active');

        this.querySelector('span').innerText =
            item.classList.contains('active') ? '−' : '+';
    });

});

/* Toast */
function showToast(){

    let toast = document.getElementById('supportToast');

    toast.classList.add('show');

    setTimeout(function(){
        toast.classList.remove('show');
    },3000);

}
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>