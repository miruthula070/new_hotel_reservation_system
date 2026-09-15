/**
 * Grand Azure Resort & Hotel Management
 * Interactive Enhancements & UI Behaviors
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Minimum Date Restriction (Default to Today for all date pickers)
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('input[type="date"]').forEach(dateInput => {
        if (!dateInput.getAttribute('min')) {
            dateInput.setAttribute('min', today);
        }
    });

    // 2. Modal Open / Close Controller
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on click outside window
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // 3. Quick Copy for OTP Codes
    window.copyOTP = function(code, buttonElement) {
        navigator.clipboard.writeText(code).then(() => {
            const originalHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
            buttonElement.classList.add('btn-emerald');
            setTimeout(() => {
                buttonElement.innerHTML = originalHTML;
                buttonElement.classList.remove('btn-emerald');
            }, 2000);
        }).catch(() => {
            // Fallback
            prompt("Copy check-in OTP:", code);
        });
    };

    // 4. Fill Demo Credentials on Login Page
    window.fillDemoLogin = function(email, password) {
        const emailInput = document.querySelector('input[name="email"]');
        const passInput = document.querySelector('input[name="password"]');
        if (emailInput && passInput) {
            emailInput.value = email;
            passInput.value = password;
            emailInput.focus();
        }
    };

    // 5. Open Booking Modal with Pre-filled Room Data (if using booking modal)
    window.prepareBookingModal = function(roomId, roomType, price) {
        const modal = document.getElementById('bookRoomModal');
        if (modal) {
            document.getElementById('modalRoomID').value = roomId;
            document.getElementById('modalRoomTitle').innerText = roomType + ' (Room #' + roomId + ')';
            document.getElementById('modalRoomPrice').innerText = 'LKR ' + Number(price).toLocaleString('en-US', { minimumFractionDigits: 2 });
            openModal('bookRoomModal');
        }
    };

    // 6. Open Modify Booking Modal
    window.prepareModifyModal = function(bookingId, checkIn, checkOut) {
        const modal = document.getElementById('modifyBookingModal');
        if (modal) {
            document.getElementById('modifyBookingID').value = bookingId;
            document.getElementById('modifyCheckIn').value = checkIn;
            document.getElementById('modifyCheckOut').value = checkOut;
            document.getElementById('modifyModalTitle').innerText = 'Modify Booking #' + bookingId;
            openModal('modifyBookingModal');
        }
    };
});
