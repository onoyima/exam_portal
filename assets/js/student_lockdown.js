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

    // 4. Silent Kiosk Enforcement (No intrusive modals)
    function enforceKiosk() {
        if (!document.fullscreenElement && !window.isReviewMode) {
            const docEl = document.documentElement;
            // Silent Attempt
            docEl.requestFullscreen().catch(() => {
                // If it fails (no gesture yet), we just wait for the next interaction
            });
        }
    }

    // High-Integrity Multi-Gesture Trigger
    function activateKiosk() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(e => console.log("Awaiting user gesture for Kiosk..."));
        }
    }

    // Attach to ALL possible interaction points for 'Automatic' feel
    ['click', 'mousedown', 'keydown', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, activateKiosk, { passive: true });
    });

    // Integrity Loop
    if (!isExamPage) {
        setInterval(enforceKiosk, 2000);
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
