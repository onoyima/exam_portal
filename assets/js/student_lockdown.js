/**
 * Phase 5 & 6: Keyboard/Shortcut Lockdown
 * Blocks escape routes (right click, F12, DevTools) without aggressive 
 * full-screen popups that disrupt the user experience.
 */
(function () {
    // Block Escape Routes
    document.addEventListener('contextmenu', e => e.preventDefault());
    
    document.addEventListener('keydown', e => {
        // Blocks: F12 (123), Escape (27)
        const blockedKeys = [123, 27];
        if (blockedKeys.includes(e.keyCode)) {
            e.preventDefault();
            return false;
        }
        
        // Ctrl shortcuts
        if (e.ctrlKey) {
            // Ctrl+U, Ctrl+P, Ctrl+S, Ctrl+Shift+I/J/C
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
})();

