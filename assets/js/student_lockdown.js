/**
 * Phase 5 & 6: Aggressive Student Kiosk Lockdown
 * Injects Power Button, Enforces Fullscreen, and Blocks all escape routes.
 * Confines student to portal via "Zero Trust" UI.
 */
(function () {
    const isExamPage = window.location.href.includes('student_objective_exam');
    const isLoginPage = window.location.href.includes('student_login');

    // 1. Injected Styles
    const style = document.createElement('style');
    style.innerHTML = `
        #globalPowerBtn {
            position: fixed; top: 15px; right: 15px; z-index: 2147483647;
            width: 50px; height: 50px; border-radius: 50%;
            background: rgba(220,53,69,0.25); border: 2px solid #dc3545;
            color: #dc3545; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.3s; backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(220,53,69,0.4);
        }
        #globalPowerBtn:hover {
            background: #dc3545; color: white; transform: scale(1.1) rotate(90deg);
            box-shadow: 0 0 30px #dc3545;
        }
        .kiosk-lock-overlay {
            position: fixed; inset: 0; background: #000; z-index: 2147483647;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: white; font-family: 'Outfit', sans-serif; text-align: center;
        }
        .bi-power-glow { animation: powerPulse 2s infinite; }
        @keyframes powerPulse {
            0% { text-shadow: 0 0 0 rgba(220,53,69,0); }
            50% { text-shadow: 0 0 20px rgba(220,53,69,0.8); }
            100% { text-shadow: 0 0 0 rgba(220,53,69,0); }
        }
    `;
    document.head.appendChild(style);

    // 2. Inject Power Button (Only gate to escape)
    const pBtn = document.createElement('div');
    pBtn.id = 'globalPowerBtn';
    pBtn.innerHTML = '<i class="bi bi-power bi-power-glow" style="font-size: 1.6rem;"></i>';
    pBtn.title = "TERMINATE SESSION / EXIT KIOSK";
    document.body.appendChild(pBtn);

    // 3. Power Off Logic
    pBtn.onclick = function() {
        const swalConfig = {
            title: 'TERMINATE SESSION?',
            text: 'Warning: This will close the secure examination environment and end your session.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'YES, EXIT PORTAL',
            background: '#0a0a0a',
            color: '#fff'
        };

        if (typeof Swal === 'undefined') {
            if(confirm("TERMINATE SESSION AND POWER OFF?")) { executeLogout(); }
            return;
        }
        Swal.fire(swalConfig).then((result) => {
            if (result.isConfirmed) { executeLogout(); }
        });
    };

    function executeLogout() {
        // Destroy session on server
        fetch('student_login?action=power_off', { method: 'POST' })
        .then(() => {
            document.body.innerHTML = `
                <div class="kiosk-lock-overlay">
                    <i class="bi bi-power bi-power-glow" style="font-size: 6rem; color: #dc3545; margin-bottom: 2rem;"></i>
                    <h1 style="font-weight: 800; letter-spacing: 3px; font-family: 'Outfit';">SYSTEM OFFLINE</h1>
                    <p style="color: #666; font-size: 1.2rem;">Secure session destroyed. Communication cut.</p>
                    <p style="margin-top: 3rem; font-size: 0.9rem; color: #444;">Portal has been safely shut down.</p>
                </div>
            `;
            // Attempt to close tab
            window.close();
            setTimeout(() => {
                window.open('','_self').close();
                window.parent.close();
                window.location.href = 'student_login?logout=1'; 
            }, 1000);
        });
    }

    // 4. Aggressive Kiosk Enforcement
    function enforceKiosk() {
        if (!document.fullscreenElement && !window.isReviewMode) {
            
            // Silent Attempt on any student page (Requires user gesture)
            const docEl = document.documentElement;
            const tryFullscreen = () => {
                if (!document.fullscreenElement) {
                    docEl.requestFullscreen().catch(() => {
                        // If silent fails, show the modal only on focus or if not login page
                        if (!isLoginPage) showLockModal();
                    });
                }
            };

            // Register silent triggers (Skip for Exam Page as it has its own Start button)
            if (!isExamPage) {
                document.addEventListener('click', tryFullscreen, { once: true });
                document.addEventListener('keydown', tryFullscreen, { once: true });
            }

            // If not login or exam page, be more vocal about it
            // Exam page has its own specialized 'Security Environment' blocker that handles gesture
            if (!isLoginPage && !isExamPage) showLockModal();
        }
    }

    function showLockModal() {
        if (typeof Swal !== 'undefined' && Swal.isVisible()) return;
        
        const msg = 'SECURE KIOSK MODE REQUIRED. You are in a monitored session. Fullscreen is mandatory.';
        if (typeof Swal === 'undefined') {
            const docEl = document.documentElement;
            if (docEl.requestFullscreen) docEl.requestFullscreen().catch(e => {});
            return;
        }

        Swal.fire({
            title: 'SECURE MODE REQUIRED',
            text: msg,
            icon: 'info',
            confirmButtonText: 'ACTIVATE SECURE KIOSK',
            allowOutsideClick: false,
            confirmButtonColor: '#198754',
            background: '#0a0a0a',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                const docEl = document.documentElement;
                if (docEl.requestFullscreen) docEl.requestFullscreen().catch(e => {});
            }
        });
    }

    // Integrity Loop (Skip for Exam Page as it manages its own security gate)
    if (!isExamPage) {
        setInterval(enforceKiosk, 3000);
        document.addEventListener('fullscreenchange', enforceKiosk);
        window.addEventListener('blur', enforceKiosk);
    }

    // 5. Block Escape Routes (Only Power Button allowed)
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', e => {
        // Blocks: F12 (123), Escape (27), Ctrl+U (85), Ctrl+Shift+I/J/C, Alt+F4 (115), Win/Cmd key
        const blockedKeys = [123, 27];
        if (blockedKeys.includes(e.keyCode)) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl shortcuts
        if (e.ctrlKey) {
            // Ctrl+U, Ctrl+P, Ctrl+S, Ctrl+Shift+I
            if ([85, 80, 83].includes(e.keyCode) || (e.shiftKey && [73, 74, 67].includes(e.keyCode))) {
                e.preventDefault();
                return false;
            }
        }

        // Alt shortcuts (Alt+F4)
        if (e.altKey && e.keyCode === 115) {
            e.preventDefault();
            return false;
        }
    });

    // 6. Entrance
    window.addEventListener('load', () => {
        setTimeout(enforceKiosk, 300);
    });
})();
