function initBreathingPage() {
    const root = document.getElementById('breathing-page');
    if (!root) return;

    const ac = new AbortController();
    const sig = ac.signal;

    const cards = Array.from(document.querySelectorAll('.exercise-item'));
    const titleEl = document.getElementById('exercise-title');
    const circleEl = document.getElementById('breath-circle');
    const ringProgressEl = document.getElementById('phase-ring-progress');
    const imageEl = document.getElementById('breathing-image');
    const phaseLabelEl = document.getElementById('phase-label');
    const phaseTimeEl = document.getElementById('phase-time');
    const phaseLiveEl = document.getElementById('phase-live');
    const cycleCurrentEl = document.getElementById('cycle-current');
    const totalTimeEl = document.getElementById('total-time');
    const sessionStateEl = document.getElementById('session-state');
    const statusEl = document.getElementById('session-status');
    const cyclesInput = document.getElementById('cycles');
    const globalProgressFillEl = document.getElementById('global-progress-fill');
    const globalProgressLabelEl = document.getElementById('global-progress-label');
    const globalProgressTrackEl = document.getElementById('global-progress-track');
    const focusBtn = document.getElementById('focus-btn');
    const userMenuEl = root.querySelector('.user-menu');
    const userMenuTriggerEl = document.getElementById('user-menu-trigger');
    const userMenuDropdownEl = document.getElementById('user-menu-dropdown');
    const completionPopupEl = document.getElementById('completion-popup');
    const completionPopupMessageEl = document.getElementById('completion-popup-message');
    const completionPopupCloseBtn = document.getElementById('completion-popup-close');
    const completionPopupRestartBtn = document.getElementById('completion-popup-restart');
    const completionPopupBackdrop = completionPopupEl ? completionPopupEl.querySelector('[data-popup-close]') : null;

    const startBtn = document.getElementById('start-btn');
    const pauseBtn = document.getElementById('pause-btn');
    const resetBtn = document.getElementById('reset-btn');

    const saveUrl = root.dataset.saveUrl || '';
    const csrfToken = root.dataset.csrfToken || '';
    const isLoggedIn = root.dataset.isLoggedIn === '1';

    const ringRadius = 112;
    const ringCircumference = 2 * Math.PI * ringRadius;
    const mobileFocusQuery = window.matchMedia('(max-width: 980px)');
    const TIMER_SPEED_FACTOR = 0.75;

    const phaseLabel = {
        ready: 'Pr\u00eat',
        inhale: 'Inspire',
        hold: 'Bloque',
        exhale: 'Expire'
    };

    const phaseColor = {
        ready: '#2b8166',
        inhale: '#2f8a6f',
        hold: '#b58e36',
        exhale: '#5b79a7'
    };

    const phaseBodyClass = ['phase-ready', 'phase-inhale', 'phase-hold', 'phase-exhale'];

    let selected = null;
    let phase = 'ready';
    let phaseDuration = 0;
    let phaseElapsed = 0;
    let cycleCurrent = 0;
    let cycleTarget = 0;
    let elapsed = 0;
    let running = false;
    let rafId = null;
    let sessionStartMs = 0;
    let phaseStartMs = 0;
    let pauseStartMs = 0;
    let pausedTotalMs = 0;
    let focusMode = false;
    let focusAutoEnabledByMobile = false;
    let userMenuOpen = false;
    let phaseSwitchTimeout = null;
    let lastFocusedBeforePopup = null;

    const inhaleImageSrc = imageEl ? imageEl.dataset.imageInhale : '';
    const holdImageSrc = imageEl ? imageEl.dataset.imageHold : '';
    const exhaleImageSrc = imageEl ? imageEl.dataset.imageExhale : '';
    const inhaleImageAbsSrc = inhaleImageSrc ? new URL(inhaleImageSrc, window.location.origin).href : '';
    const holdImageAbsSrc = holdImageSrc ? new URL(holdImageSrc, window.location.origin).href : '';
    const exhaleImageAbsSrc = exhaleImageSrc ? new URL(exhaleImageSrc, window.location.origin).href : '';

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

    const getSafeCycleTarget = () => {
        const raw = parseInt(cyclesInput.value || '6', 10);
        const safeValue = clamp(Number.isNaN(raw) ? 6 : raw, 1, 60);
        cyclesInput.value = String(safeValue);
        return safeValue;
    };

    const formatSeconds = (value) => `${Math.max(0, Math.floor(value))}s`;

    const getTotalTargetSeconds = () => {
        if (!selected || cycleTarget <= 0) return 0;
        return (selected.inhale + selected.hold + selected.exhale) * cycleTarget;
    };

    const updateRingProgress = (progress) => {
        if (!ringProgressEl) return;
        const normalized = clamp(progress, 0, 1);
        const offset = ringCircumference * (1 - normalized);
        ringProgressEl.style.strokeDashoffset = offset.toFixed(2);
    };

    const setBodyPhase = (nextPhase) => {
        document.body.classList.remove(...phaseBodyClass);
        document.body.classList.add(`phase-${nextPhase}`);
    };

    const setSessionState = (label, stateKey) => {
        sessionStateEl.textContent = label;
        sessionStateEl.dataset.state = stateKey;
    };

    const setUserMenuOpen = (isOpen) => {
        if (!userMenuEl || !userMenuTriggerEl) return;
        userMenuOpen = isOpen;
        userMenuEl.classList.toggle('is-open', isOpen);
        userMenuTriggerEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const openCompletionPopup = (message) => {
        if (!completionPopupEl) return;
        lastFocusedBeforePopup = document.activeElement;
        if (completionPopupMessageEl) {
            completionPopupMessageEl.textContent = message;
        }
        completionPopupEl.hidden = false;
        if (completionPopupRestartBtn) {
            completionPopupRestartBtn.focus();
        } else if (completionPopupCloseBtn) {
            completionPopupCloseBtn.focus();
        }
    };

    const closeCompletionPopup = () => {
        if (!completionPopupEl || completionPopupEl.hidden) return;
        completionPopupEl.hidden = true;
        if (lastFocusedBeforePopup && typeof lastFocusedBeforePopup.focus === 'function') {
            lastFocusedBeforePopup.focus();
        }
        lastFocusedBeforePopup = null;
    };

    const setPrimaryButton = (button, isPrimary) => {
        button.classList.toggle('btn-primary', isPrimary);
        button.classList.toggle('btn-soft', !isPrimary);
    };

    const flashPhaseChange = () => {
        circleEl.classList.add('is-switching');
        if (phaseSwitchTimeout) {
            clearTimeout(phaseSwitchTimeout);
        }
        phaseSwitchTimeout = setTimeout(() => {
            circleEl.classList.remove('is-switching');
            phaseSwitchTimeout = null;
        }, 260);
    };

    const syncPhaseVisual = (nextPhase) => {
        circleEl.classList.remove('phase-inhale', 'phase-hold', 'phase-exhale');
        if (imageEl) {
            imageEl.classList.remove('phase-inhale', 'phase-hold', 'phase-exhale');
        }

        if (nextPhase === 'ready') return;

        circleEl.classList.add(`phase-${nextPhase}`);
        if (imageEl) {
            imageEl.classList.add(`phase-${nextPhase}`);
        }
    };

    const syncPhaseImage = (nextPhase) => {
        if (!imageEl) return;

        if (nextPhase === 'inhale') {
            if (inhaleImageSrc && imageEl.src !== inhaleImageAbsSrc) imageEl.src = inhaleImageSrc;
        } else if (nextPhase === 'hold') {
            if (holdImageSrc && imageEl.src !== holdImageAbsSrc) imageEl.src = holdImageSrc;
        } else if (nextPhase === 'exhale') {
            if (exhaleImageSrc && imageEl.src !== exhaleImageAbsSrc) imageEl.src = exhaleImageSrc;
        } else if (inhaleImageSrc && imageEl.src !== inhaleImageAbsSrc) {
            imageEl.src = inhaleImageSrc;
        }

        const duration = nextPhase === 'ready' ? 0.25 : Math.max(0.2, phaseDuration);
        imageEl.style.transitionDuration = `${duration}s`;
    };

    const announcePhase = (nextPhase) => {
        if (!phaseLiveEl) return;
        if (nextPhase === 'ready') {
            phaseLiveEl.textContent = 'Session pr\u00eate';
            return;
        }
        phaseLiveEl.textContent = `${phaseLabel[nextPhase]} pendant ${Math.round(phaseDuration)} secondes`;
    };

    const setPhase = (nextPhase, nowMs = performance.now(), carrySeconds = 0) => {
        phase = nextPhase;
        phaseDuration = 0;

        if (nextPhase === 'inhale') {
            phaseDuration = selected ? selected.inhale : 0;
        } else if (nextPhase === 'hold') {
            phaseDuration = selected ? selected.hold : 0;
        } else if (nextPhase === 'exhale') {
            phaseDuration = selected ? selected.exhale : 0;
        }

        const maxCarry = phaseDuration > 0 ? phaseDuration : 0;
        phaseElapsed = clamp(carrySeconds, 0, maxCarry);
        phaseStartMs = nowMs - (phaseElapsed * 1000);

        phaseLabelEl.textContent = phaseLabel[nextPhase];
        if (nextPhase === 'ready' || phaseDuration <= 0) {
            phaseTimeEl.textContent = '0s';
        } else {
            const remaining = Math.max(1, Math.ceil(phaseDuration - phaseElapsed));
            phaseTimeEl.textContent = `${remaining}s`;
        }

        if (ringProgressEl) {
            ringProgressEl.style.stroke = phaseColor[nextPhase];
        }

        updateRingProgress(0);
        setBodyPhase(nextPhase);
        syncPhaseVisual(nextPhase);
        syncPhaseImage(nextPhase);
        announcePhase(nextPhase);
        flashPhaseChange();
    };

    const updateGlobalProgress = () => {
        const totalTarget = getTotalTargetSeconds();
        const progress = totalTarget > 0 ? clamp(elapsed / totalTarget, 0, 1) : 0;
        const percent = Math.round(progress * 100);

        if (globalProgressFillEl) {
            globalProgressFillEl.style.transform = `scaleX(${progress})`;
        }
        if (globalProgressLabelEl) {
            globalProgressLabelEl.textContent = `${percent}%`;
        }
        if (globalProgressTrackEl) {
            globalProgressTrackEl.setAttribute('aria-valuenow', String(percent));
        }
    };

    const updateStats = () => {
        cycleCurrentEl.textContent = `${cycleCurrent} / ${cycleTarget || 0}`;
        totalTimeEl.textContent = formatSeconds(elapsed);
    };

    const updatePhaseClock = () => {
        if (phase === 'ready' || phaseDuration <= 0) {
            phaseTimeEl.textContent = '0s';
            updateRingProgress(0);
            return;
        }

        const progress = clamp(phaseElapsed / phaseDuration, 0, 1);
        const remaining = Math.max(1, Math.ceil(phaseDuration - phaseElapsed));
        phaseTimeEl.textContent = `${remaining}s`;
        updateRingProgress(progress);
    };

    const updateControlStates = () => {
        if (!selected) {
            startBtn.disabled = true;
            pauseBtn.disabled = true;
            resetBtn.disabled = true;
            cyclesInput.disabled = true;
            if (focusBtn) focusBtn.disabled = true;
            return;
        }

        cyclesInput.disabled = false;
        if (focusBtn) focusBtn.disabled = false;

        const hasProgress = elapsed > 0;
        resetBtn.disabled = !hasProgress && phase === 'ready' && !running;
        setPrimaryButton(resetBtn, false);

        if (running) {
            startBtn.disabled = true;
            pauseBtn.disabled = false;
            pauseBtn.textContent = 'Pause';
            setPrimaryButton(startBtn, false);
            setPrimaryButton(pauseBtn, true);
            return;
        }

        if (phase !== 'ready') {
            startBtn.disabled = true;
            pauseBtn.disabled = false;
            pauseBtn.textContent = 'Reprendre';
            setPrimaryButton(startBtn, false);
            setPrimaryButton(pauseBtn, true);
            return;
        }

        startBtn.disabled = false;
        pauseBtn.disabled = true;
        pauseBtn.textContent = 'Pause';
        setPrimaryButton(startBtn, true);
        setPrimaryButton(pauseBtn, false);
    };

    const updateUi = () => {
        updatePhaseClock();
        updateStats();
        updateGlobalProgress();
        updateControlStates();
    };

    const stopLoop = () => {
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    };

    const startLoop = () => {
        stopLoop();
        rafId = requestAnimationFrame(onFrame);
    };

    const saveLaunch = async () => {
        if (!isLoggedIn || !selected || !saveUrl || !csrfToken) return;
        try {
            await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    exercise_id: selected.id,
                    cycle_count: cycleTarget,
                    total_seconds: Math.max(1, Math.round(elapsed))
                })
            });
        } catch (error) {
            // Ignore save errors to avoid breaking the client session UX.
        }
    };

    const finishSession = (nowMs = performance.now()) => {
        running = false;
        stopLoop();
        elapsed = getTotalTargetSeconds();
        cycleCurrent = cycleTarget;
        setPhase('ready', nowMs, 0);
        setSessionState('Termin\u00e9', 'done');
        statusEl.textContent = 'Session termin\u00e9e. Bon travail.';
        updateUi();
        openCompletionPopup(`F\u00e9licitations\u00a0! Vous avez termin\u00e9 ${cycleTarget} cycle${cycleTarget > 1 ? 's' : ''} en ${Math.round(elapsed)} secondes.`);
        saveLaunch();
    };

    const goToNextPhase = (nowMs) => {
        if (phase === 'inhale') {
            setPhase('hold', nowMs, 0);
            return true;
        }
        if (phase === 'hold') {
            setPhase('exhale', nowMs, 0);
            return true;
        }
        if (phase === 'exhale') {
            if (cycleCurrent >= cycleTarget) {
                finishSession(nowMs);
                return false;
            }
            cycleCurrent += 1;
            setPhase('inhale', nowMs, 0);
            return true;
        }
        return false;
    };

    const advance = (nowMs) => {
        if (!running || !selected || phase === 'ready') return;

        elapsed = Math.max(0, ((nowMs - sessionStartMs - pausedTotalMs) / 1000) * TIMER_SPEED_FACTOR);
        phaseElapsed = Math.max(0, ((nowMs - phaseStartMs) / 1000) * TIMER_SPEED_FACTOR);

        let guard = 0;
        while (running && guard < 12) {
            if (phaseDuration <= 0) {
                const canContinue = goToNextPhase(nowMs);
                if (!canContinue || !running) return;
                phaseElapsed = Math.max(0, ((nowMs - phaseStartMs) / 1000) * TIMER_SPEED_FACTOR);
                guard += 1;
                continue;
            }

            if (phaseElapsed < phaseDuration) {
                break;
            }

            const canContinue = goToNextPhase(nowMs);
            if (!canContinue || !running) return;
            phaseElapsed = Math.max(0, ((nowMs - phaseStartMs) / 1000) * TIMER_SPEED_FACTOR);
            guard += 1;
        }
    };

    function onFrame(now) {
        if (!running) return;
        advance(now);
        updateUi();

        if (running) {
            rafId = requestAnimationFrame(onFrame);
        }
    }

    const resetSession = () => {
        stopLoop();
        running = false;
        closeCompletionPopup();
        cycleTarget = getSafeCycleTarget();
        cycleCurrent = 0;
        elapsed = 0;
        sessionStartMs = 0;
        phaseStartMs = 0;
        pauseStartMs = 0;
        pausedTotalMs = 0;
        setPhase('ready', performance.now(), 0);
        setSessionState('Pr\u00eat', 'ready');
        statusEl.textContent = '';
        updateUi();
    };

    const startSession = () => {
        if (!selected) {
            statusEl.textContent = 'S\u00e9lectionne un exercice.';
            return;
        }
        if (running) return;
        if (phase !== 'ready') return;

        closeCompletionPopup();
        const now = performance.now();
        cycleTarget = getSafeCycleTarget();
        cycleCurrent = 1;
        elapsed = 0;
        sessionStartMs = now;
        pausedTotalMs = 0;
        pauseStartMs = 0;
        setPhase('inhale', now, 0);
        setSessionState('En cours', 'running');
        statusEl.textContent = '';
        running = true;
        updateUi();
        startLoop();
    };

    const pauseSession = () => {
        if (!running) return;
        const now = performance.now();
        advance(now);
        updateUi();
        running = false;
        stopLoop();
        pauseStartMs = now;
        setSessionState('Pause', 'pause');
        statusEl.textContent = 'Session en pause.';
        updateUi();
    };

    const resumeSession = () => {
        if (running || phase === 'ready') return;
        const now = performance.now();
        if (pauseStartMs > 0) {
            const pausedMs = Math.max(0, now - pauseStartMs);
            pausedTotalMs += pausedMs;
            phaseStartMs += pausedMs;
            pauseStartMs = 0;
        }
        running = true;
        setSessionState('En cours', 'running');
        statusEl.textContent = '';
        updateUi();
        startLoop();
    };

    const setFocusMode = (enabled) => {
        focusMode = enabled;
        root.classList.toggle('is-focus', enabled);
        document.body.classList.toggle('focus-mode', enabled);
        if (focusBtn) {
            focusBtn.textContent = enabled ? 'Quitter focus' : 'Mode focus';
            focusBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        }
    };

    const syncResponsiveFocusMode = () => {
        if (mobileFocusQuery.matches) {
            if (!focusMode) {
                setFocusMode(true);
                focusAutoEnabledByMobile = true;
            }
            return;
        }

        if (focusAutoEnabledByMobile) {
            setFocusMode(false);
            focusAutoEnabledByMobile = false;
        }
    };

    const applySelected = (exercise) => {
        selected = exercise;
        titleEl.textContent = exercise.name;
        resetSession();
    };

    if (ringProgressEl) {
        ringProgressEl.style.strokeDasharray = ringCircumference.toFixed(2);
        ringProgressEl.style.strokeDashoffset = ringCircumference.toFixed(2);
    }

    cards.forEach((card) => {
        card.addEventListener('click', () => {
            cards.forEach((item) => item.classList.remove('is-active'));
            card.classList.add('is-active');

            applySelected({
                id: parseInt(card.dataset.id, 10),
                name: card.dataset.name,
                inhale: parseInt(card.dataset.inhale, 10),
                hold: parseInt(card.dataset.hold, 10),
                exhale: parseInt(card.dataset.exhale, 10)
            });
        }, { signal: sig });
    });

    startBtn.addEventListener('click', startSession, { signal: sig });
    resetBtn.addEventListener('click', resetSession, { signal: sig });
    pauseBtn.addEventListener('click', () => {
        if (running) {
            pauseSession();
        } else {
            resumeSession();
        }
    }, { signal: sig });

    cyclesInput.addEventListener('change', () => {
        getSafeCycleTarget();
        if (phase === 'ready' && !running) {
            cycleTarget = getSafeCycleTarget();
            updateUi();
        }
    }, { signal: sig });

    if (focusBtn) {
        focusBtn.addEventListener('click', () => {
            setUserMenuOpen(false);
            setFocusMode(!focusMode);
            focusAutoEnabledByMobile = false;
        }, { signal: sig });
    }

    if (userMenuTriggerEl) {
        userMenuTriggerEl.addEventListener('click', (event) => {
            event.preventDefault();
            setUserMenuOpen(!userMenuOpen);
        }, { signal: sig });
    }

    if (userMenuDropdownEl) {
        userMenuDropdownEl.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setUserMenuOpen(false);
                if (userMenuTriggerEl) {
                    userMenuTriggerEl.focus();
                }
            }
        }, { signal: sig });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && completionPopupEl && !completionPopupEl.hidden) {
            closeCompletionPopup();
            return;
        }
        if (event.key === 'Escape' && userMenuOpen) {
            setUserMenuOpen(false);
            if (userMenuTriggerEl) {
                userMenuTriggerEl.focus();
            }
            return;
        }
        if (event.key === 'Escape' && focusMode) {
            setFocusMode(false);
            if (focusBtn) focusBtn.focus();
        }
    }, { signal: sig });

    document.addEventListener('click', (event) => {
        if (!userMenuOpen || !userMenuEl) return;
        if (!userMenuEl.contains(event.target)) {
            setUserMenuOpen(false);
        }
    }, { signal: sig });

    document.addEventListener('focusin', (event) => {
        if (!userMenuOpen || !userMenuEl) return;
        if (!userMenuEl.contains(event.target)) {
            setUserMenuOpen(false);
        }
    }, { signal: sig });

    if (completionPopupCloseBtn) {
        completionPopupCloseBtn.addEventListener('click', closeCompletionPopup, { signal: sig });
    }

    if (completionPopupBackdrop) {
        completionPopupBackdrop.addEventListener('click', closeCompletionPopup, { signal: sig });
    }

    if (completionPopupRestartBtn) {
        completionPopupRestartBtn.addEventListener('click', () => {
            closeCompletionPopup();
            resetSession();
            startSession();
        }, { signal: sig });
    }

    if (imageEl) {
        if (holdImageSrc) {
            const preloadHold = new Image();
            preloadHold.src = holdImageSrc;
        }
        if (exhaleImageSrc) {
            const preloadExhale = new Image();
            preloadExhale.src = exhaleImageSrc;
        }
        imageEl.addEventListener('error', () => {
            if (inhaleImageSrc) {
                imageEl.src = inhaleImageSrc;
            }
        }, { signal: sig });
    }

    if (typeof mobileFocusQuery.addEventListener === 'function') {
        mobileFocusQuery.addEventListener('change', syncResponsiveFocusMode, { signal: sig });
    } else if (typeof mobileFocusQuery.addListener === 'function') {
        mobileFocusQuery.addListener(syncResponsiveFocusMode);
    }

    // Cleanup RAF and all listeners when Turbo is about to cache the page
    document.addEventListener('turbo:before-cache', () => {
        stopLoop();
        ac.abort();
    }, { signal: sig });

    // Auto-select first exercise
    if (cards.length > 0) {
        cards[0].click();
    } else {
        statusEl.textContent = 'Aucun exercice actif disponible.';
        selected = null;
        setPhase('ready');
        setSessionState('Pr\u00eat', 'ready');
        updateUi();
    }

    setBodyPhase('ready');
    setSessionState('Pr\u00eat', 'ready');
    cycleTarget = getSafeCycleTarget();
    updateUi();
    syncResponsiveFocusMode();
}

document.addEventListener('turbo:load', initBreathingPage);
