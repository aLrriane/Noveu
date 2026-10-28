document.addEventListener("DOMContentLoaded", function() {

    // 1. DARK MODE LOGIC
    const themeToggle = document.getElementById('theme-toggle');
    const body = document.body;
    const icon = themeToggle ? themeToggle.querySelector('i') : null;

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        if (icon) icon.className = 'fas fa-sun';
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                if (icon) icon.className = 'fas fa-sun';
            } else {
                localStorage.setItem('theme', 'light');
                if (icon) icon.className = 'fas fa-moon';
            }
        });
    }

    // 2. SWEETALERT DELETE CONFIRMATION
    const deleteForms = document.querySelectorAll('form');
    deleteForms.forEach(form => {
        const deleteBtn = form.querySelector('button[name^="delete_"]');
        if (deleteBtn) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    background: document.body.classList.contains('dark-mode') ? '#2d2d2d' : '#fff',
                    color: document.body.classList.contains('dark-mode') ? '#fff' : '#333'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            });
        }
    });

    // 3. SCROLL TO TOP
    const scrollBtn = document.getElementById('scrollTop');
    if (scrollBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) scrollBtn.classList.add('visible');
            else scrollBtn.classList.remove('visible');
        });
        scrollBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // 4. HERO SLIDESHOW
    const slides = document.querySelectorAll('.hero-slide');
    let currentSlide = 0;
    if (slides.length > 0) {
        slides[0].classList.add('active');
        setInterval(() => {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 4000);
    }
});

// 5. SEE MORE TOGGLE
function toggleSection(sectionId, btnId) {
    const section = document.getElementById(sectionId);
    const btn = document.getElementById(btnId);
    if (window.getComputedStyle(section).display === 'none') {
        section.style.display = 'grid';
        btn.innerHTML = 'See Less <i class="fas fa-chevron-up"></i>';
    } else {
        section.style.display = 'none';
        btn.innerHTML = 'See All <i class="fas fa-chevron-down"></i>';
    }
}

// ========================================
// 6. BEAUTIFUL POPUP FUNCTIONS (NEW)
// ========================================

// Small "Toast" (Top Right, Auto Close) - Good for "Saved" or "Updated"
function showToast(icon, title) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: document.body.classList.contains('dark-mode') ? '#2d2d2d' : '#fff',
        color: document.body.classList.contains('dark-mode') ? '#fff' : '#333',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    Toast.fire({ icon: icon, title: title });
}

// Center Popup (Modal) - Good for "Success! Redirecting..." or "Error!"
function showPopup(icon, title, text, redirectUrl = null) {
    Swal.fire({
        icon: icon,
        title: title,
        text: text,
        confirmButtonColor: '#f47521',
        background: document.body.classList.contains('dark-mode') ? '#2d2d2d' : '#fff',
        color: document.body.classList.contains('dark-mode') ? '#fff' : '#333'
    }).then((result) => {
        if (redirectUrl) {
            window.location.href = redirectUrl;
        }
    });
}

// ========================================
// NOTIFICATION LOGIC
// ========================================
function openNotif() {
    document.getElementById('notifSidebar').classList.add('open');
    document.getElementById('notifOverlay').classList.add('open');
}

function closeNotif() {
    document.getElementById('notifSidebar').classList.remove('open');
    document.getElementById('notifOverlay').classList.remove('open');
}

function switchTab(tabName) {
    // 1. Hide all content lists
    document.querySelectorAll('.notif-list').forEach(el => el.classList.remove('active'));

    // 2. Show target list
    const target = document.getElementById('tab-' + tabName);
    if (target) target.classList.add('active');

    // 3. Update button styles
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
}


// ========================================
// 7. VIEW TOGGLE LOGIC (Grid vs List)
// ========================================
function setView(mode) {
    const container = document.getElementById('novelContainer');
    const btnGrid = document.getElementById('btnGrid');
    const btnList = document.getElementById('btnList');

    if (!container) return; // Exit if page has no grid

    if (mode === 'list') {
        container.classList.remove('novel-grid');
        container.classList.add('novel-list');

        if (btnList) btnList.classList.add('active');
        if (btnGrid) btnGrid.classList.remove('active');

        localStorage.setItem('novelViewMode', 'list');
    } else {
        container.classList.remove('novel-list');
        container.classList.add('novel-grid');

        if (btnGrid) btnGrid.classList.add('active');
        if (btnList) btnList.classList.remove('active');

        localStorage.setItem('novelViewMode', 'grid');
    }
}

// Apply saved view on page load
document.addEventListener("DOMContentLoaded", function() {
    const savedMode = localStorage.getItem('novelViewMode') || 'grid';
    setView(savedMode);
});