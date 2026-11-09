/**
 * AI PROCTORING MODULE — Level 1 Face Detection
 * Uses TensorFlow.js BlazeFace to detect face presence & count in real-time.
 * Integrates with the existing proctor_ping violation pipeline.
 * All inference runs client-side — no GPU server required.
 *
 * Violations reported:
 *   FACE_NOT_VISIBLE  — No face detected in webcam frame
 *   MULTIPLE_FACES    — More than one face detected (potential accomplice)
 */
(function () {
    'use strict';

    // ─── Configuration ────────────────────────────────────────────────────────
    const DETECTION_INTERVAL_MS  = 4000;  // Run detection every 4 seconds
    const VIOLATION_COOLDOWN_MS  = 18000; // Minimum gap between same violation type
    const FACE_CONFIDENCE_THRESH = 0.72;  // BlazeFace probability threshold
    const STARTUP_DELAY_MS       = 5000;  // Wait after exam start before scanning

    // ─── State ────────────────────────────────────────────────────────────────
    let detector        = null;
    let inferenceVideo  = null;
    let isRunning       = false;
    let lastViolation   = {};   // { [type]: timestamp }
    let audioCtx        = null;
    let consecutiveErrors = 0;

    // ─── Web Audio API Alarm Generator ────────────────────────────────────────
    // Produces alarm tones entirely in the browser — works 100% offline.

    function getAudioCtx() {
        if (!audioCtx || audioCtx.state === 'closed') {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    /**
     * Play a patterned alarm tone.
     * @param {'noface'|'multiFace'|'strike'} alarmType
     */
    function playAlarm(alarmType) {
        try {
            const ctx = getAudioCtx();

            // Define frequency patterns per alarm type
            const patterns = {
                noface:    [
                    { freq: 600,  t: 0.00, dur: 0.18, type: 'sine'    },
                    { freq: 400,  t: 0.24, dur: 0.30, type: 'sine'    }
                ],
                multiFace: [
                    { freq: 1100, t: 0.00, dur: 0.10, type: 'sawtooth' },
                    { freq: 880,  t: 0.14, dur: 0.10, type: 'sawtooth' },
                    { freq: 1100, t: 0.28, dur: 0.10, type: 'sawtooth' },
                    { freq: 880,  t: 0.42, dur: 0.25, type: 'sawtooth' }
                ],
                strike:    [
                    { freq: 880,  t: 0.00, dur: 0.12, type: 'sawtooth' },
                    { freq: 880,  t: 0.18, dur: 0.12, type: 'sawtooth' },
                    { freq: 1200, t: 0.36, dur: 0.30, type: 'sawtooth' }
                ]
            };

            const tones = patterns[alarmType] || patterns.strike;
            tones.forEach(({ freq, t, dur, type }) => {
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = type;
                osc.frequency.setValueAtTime(freq, ctx.currentTime + t);
                gain.gain.setValueAtTime(0.40, ctx.currentTime + t);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + dur);
                osc.start(ctx.currentTime + t);
                osc.stop(ctx.currentTime + t + dur + 0.05);
            });
        } catch (e) {
            // Silent — audio context may be blocked before user interaction
        }
    }

    // ─── On-Screen Alert Toast ─────────────────────────────────────────────────
    function showAIAlert(message, alarmType) {
        // Ensure style exists
        if (!document.getElementById('_aiProcStyle')) {
            const s = document.createElement('style');
            s.id = '_aiProcStyle';
            s.textContent = `
                #_aiProcToast {
                    position: fixed; top: 72px; left: 50%;
                    transform: translateX(-50%) scale(0.9);
                    padding: 11px 26px; border-radius: 12px;
                    font-family: 'Outfit', sans-serif; font-weight: 800;
                    font-size: 0.82rem; letter-spacing: 0.6px;
                    color: #fff; z-index: 99999; display: none;
                    pointer-events: none; text-align: center;
                    border: 1px solid rgba(255,255,255,0.18);
                    backdrop-filter: blur(12px);
                    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
                    transition: opacity 0.25s ease, transform 0.25s ease;
                    max-width: 380px;
                }
                #_aiProcToast.show {
                    display: block;
                    transform: translateX(-50%) scale(1);
                    animation: _aiSlideIn 0.25s ease forwards;
                }
                @keyframes _aiSlideIn {
                    from { opacity:0; transform: translateX(-50%) translateY(-12px) scale(0.95); }
                    to   { opacity:1; transform: translateX(-50%) translateY(0)     scale(1);    }
                }
            `;
            document.head.appendChild(s);
        }

        let toast = document.getElementById('_aiProcToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = '_aiProcToast';
            document.body.appendChild(toast);
        }

        const colours = {
            noface:    '#dc2626',
            multiFace: '#7c3aed',
            strike:    '#d97706'
        };
        const icons = {
            noface:    '👤',
            multiFace: '🚨',
            strike:    '⚠️'
        };
        toast.style.background  = colours[alarmType] || '#dc2626';
        toast.innerHTML = `${icons[alarmType] || '⚠️'} &nbsp;AI PROCTOR: ${message}`;
        toast.classList.add('show');

        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.classList.remove('show');
                toast.style.opacity = '';
            }, 300);
        }, 5000);
    }

    // ─── Violation Rate Limiter ────────────────────────────────────────────────
    function canReport(type) {
        const now = Date.now();
        if (!lastViolation[type] || (now - lastViolation[type]) >= VIOLATION_COOLDOWN_MS) {
            lastViolation[type] = now;
            return true;
        }
        return false;
    }

    // ─── Send Violation Through Existing Pipeline ──────────────────────────────
    function reportViolation(type, details) {
        // Prefer the exposed window.sendPing from the exam control unit
        if (typeof window.sendPing === 'function') {
            window.sendPing('blur', type, details);
            return;
        }
        // Fallback: direct fetch to proctor_ping
        const examId    = window.examId;
        const attemptNo = window.currentAttemptNo || 1;
        if (!examId) return;
        const fd = new FormData();
        fd.append('exam_id',        examId);
        fd.append('attempt_no',     attemptNo);
        fd.append('status',         'blur');
        fd.append('violation_type', type);
        fd.append('details',        details);
        fetch('proctor_ping', { method: 'POST', body: fd }).catch(() => {});
    }

    // ─── Detection Logic ──────────────────────────────────────────────────────
    async function runDetectionCycle() {
        if (!detector || !inferenceVideo) return;
        if (window.isLocked || window.isReviewMode) return;
        if (inferenceVideo.readyState < 2) return; // Video not ready

        try {
            const faces = await detector.detect(inferenceVideo, FACE_CONFIDENCE_THRESH);
            const count = faces.length;
            consecutiveErrors = 0;

            if (count === 0) {
                if (canReport('FACE_NOT_VISIBLE')) {
                    playAlarm('noface');
                    showAIAlert('No face detected in camera!', 'noface');
                    reportViolation('FACE_NOT_VISIBLE', 'No face was detected in the webcam frame.');
                    console.warn('[AI Proctor] FACE_NOT_VISIBLE');
                }
            } else if (count > 1) {
                if (canReport('MULTIPLE_FACES')) {
                    playAlarm('multiFace');
                    showAIAlert(`${count} faces detected in camera!`, 'multiFace');
                    reportViolation('MULTIPLE_FACES', `${count} faces detected in webcam frame — possible accomplice.`);
                    console.warn('[AI Proctor] MULTIPLE_FACES:', count);
                }
            }
            // count === 1: normal, no action needed

        } catch (err) {
            consecutiveErrors++;
            if (consecutiveErrors >= 5) {
                console.error('[AI Proctor] Too many consecutive errors — stopping.', err);
                isRunning = false;
            }
        }
    }

    // ─── Model Initialisation ─────────────────────────────────────────────────
    async function loadModel() {
        try {
            if (typeof window.cbtFaceDetector === 'undefined' || typeof tf === 'undefined') {
                console.warn('[AI Proctor] Face detector not loaded.');
                return false;
            }
            // The model ships with the portal, so this works on an offline exam LAN.
            detector = await window.cbtFaceDetector.load('assets/vendor/blazeface/model/blazeface-front.json');
            console.log('[AI Proctor] Face detection model ready.');
            return true;
        } catch (e) {
            console.warn('[AI Proctor] Model load failed:', e.message);
            return false;
        }
    }

    // ─── Create Inference Video Element ──────────────────────────────────────
    function setupInferenceVideo(stream) {
        inferenceVideo = document.createElement('video');
        inferenceVideo.muted        = true;
        inferenceVideo.autoplay     = true;
        inferenceVideo.playsInline  = true;
        inferenceVideo.width        = 320;
        inferenceVideo.height       = 240;
        // Visually hidden but must be in DOM for readyState to advance
        inferenceVideo.style.cssText = 'position:fixed;top:-2px;left:-2px;width:1px;height:1px;opacity:0;pointer-events:none;';
        inferenceVideo.srcObject = stream;
        document.body.appendChild(inferenceVideo);
        inferenceVideo.play().catch(() => {});
    }

    // ─── Public Initialisation ────────────────────────────────────────────────
    window.startAIProctor = async function () {
        if (isRunning) return;

        // Only run when webcam proctoring is enabled
        if (!window.pWebcam) {
            console.log('[AI Proctor] Webcam proctoring is disabled — skipping.');
            return;
        }

        // Wait for webcam stream (set by window.initWebcam fix)
        let waited = 0;
        while (!window.webcamStream && waited < 30) {
            await new Promise(r => setTimeout(r, 500));
            waited++;
        }

        if (!window.webcamStream) {
            console.warn('[AI Proctor] No webcam stream available — AI detection disabled.');
            return;
        }

        const loaded = await loadModel();
        if (!loaded) return;

        setupInferenceVideo(window.webcamStream);
        isRunning = true;

        // Give video element time to start receiving frames
        await new Promise(r => setTimeout(r, 1500));

        console.log('[AI Proctor] Starting detection loop (every ' + DETECTION_INTERVAL_MS + 'ms)…');
        setInterval(runDetectionCycle, DETECTION_INTERVAL_MS);
    };

    // ─── Auto-start on DOM ready (no student action required) ─────────────────
    window.addEventListener('load', () => {
        setTimeout(window.startAIProctor, STARTUP_DELAY_MS + 2000);
    });

    // ─── Expose playAlarm globally for the main exam script ──────────────────
    window.playAIAlarm = playAlarm;

})();
