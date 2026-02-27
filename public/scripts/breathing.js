(() => {
    const root = document.getElementById('breathing-page');
    if (!root) return;

    const cards = Array.from(document.querySelectorAll('.exercise-item'));
    const titleEl = document.getElementById('exercise-title');
    const patternEl = document.getElementById('exercise-pattern');
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

    const phaseLabel = {
        ready: 'Pret',
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
    let lastFrameMs = 0;
    let focusMode = false;
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
            phaseLiveEl.textContent = 'Session prete';
            return;
        }
        phaseLiveEl.textContent = `${phaseLabel[nextPhase]} pendant ${Math.round(phaseDuration)} secondes`;
    };

    const setPhase = (nextPhase) => {
        phase = nextPhase;
        phaseDuration = 0;
        phaseElapsed = 0;

        if (nextPhase === 'inhale') {
            phaseDuration = selected ? selected.inhale : 0;
        } else if (nextPhase === 'hold') {
            phaseDuration = selected ? selected.hold : 0;
        } else if (nextPhase === 'exhale') {
            phaseDuration = selected ? selected.exhale : 0;
        }

        phaseLabelEl.textContent = phaseLabel[nextPhase];
        phaseTimeEl.textContent = nextPhase === 'ready' ? '0s' : `${Math.round(phaseDuration)}s`;

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
        lastFrameMs = 0;
    };

    const startLoop = () => {
        stopLoop();
        lastFrameMs = performance.now();
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

    const finishSession = () => {
        running = false;
        stopLoop();
        elapsed = getTotalTargetSeconds();
        cycleCurrent = cycleTarget;
        setPhase('ready');
        setSessionState('Termine', 'done');
        statusEl.textContent = 'Session terminee. Bon travail.';
        updateUi();
        openCompletionPopup(`Felicitations ! Vous avez termine ${cycleTarget} cycle${cycleTarget > 1 ? 's' : ''} en ${Math.round(elapsed)} secondes.`);
        saveLaunch();
    };

    const goToNextPhase = () => {
        if (phase === 'inhale') {
            setPhase('hold');
            return true;
        }
        if (phase === 'hold') {
            setPhase('exhale');
            return true;
        }
        if (phase === 'exhale') {
            if (cycleCurrent >= cycleTarget) {
                finishSession();
                return false;
            }
            cycleCurrent += 1;
            setPhase('inhale');
            return true;
        }
        return false;
    };

    const advance = (deltaSeconds) => {
        if (!running || !selected || phase === 'ready') return;

        elapsed += deltaSeconds;
        phaseElapsed += deltaSeconds;

        let guard = 0;
        while (phaseDuration > 0 && phaseElapsed >= phaseDuration && running && guard < 12) {
            const overflow = phaseElapsed - phaseDuration;
            const canContinue = goToNextPhase();
            if (!canContinue || !running) return;
            phaseElapsed = overflow;
            guard += 1;
        }
    };

    function onFrame(now) {
        if (!running) return;
        const delta = clamp((now - lastFrameMs) / 1000, 0, 0.25);
        lastFrameMs = now;

        advance(delta);
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
        setPhase('ready');
        setSessionState('Pret', 'ready');
        statusEl.textContent = '';
        updateUi();
    };

    const startSession = () => {
        if (!selected) {
            statusEl.textContent = 'Selectionne un exercice.';
            return;
        }
        if (running) return;
        if (phase !== 'ready') return;

        closeCompletionPopup();
        cycleTarget = getSafeCycleTarget();
        cycleCurrent = 1;
        elapsed = 0;
        setPhase('inhale');
        setSessionState('En cours', 'running');
        statusEl.textContent = '';
        running = true;
        updateUi();
        startLoop();
    };

    const pauseSession = () => {
        if (!running) return;
        running = false;
        stopLoop();
        setSessionState('Pause', 'pause');
        statusEl.textContent = 'Session en pause.';
        updateUi();
    };

    const resumeSession = () => {
        if (running || phase === 'ready') return;
        running = true;
        setSessionState('En cours', 'running');
        statusEl.textContent = '';
        updateUi();
        startLoop();
    };

    const setFocusMode = (enabled) => {
        focusMode = enabled;
        root.classList.toggle('is-focus', enabled);
        if (focusBtn) {
            focusBtn.textContent = enabled ? 'Quitter focus' : 'Mode focus';
            focusBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        }
    };

    const applySelected = (exercise) => {
        selected = exercise;
        titleEl.textContent = exercise.name;
        patternEl.textContent = `Pattern: Inspire ${exercise.inhale}s - Bloque ${exercise.hold}s - Expire ${exercise.exhale}s`;
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
        });
    });

    startBtn.addEventListener('click', startSession);
    resetBtn.addEventListener('click', resetSession);
    pauseBtn.addEventListener('click', () => {
        if (running) {
            pauseSession();
        } else {
            resumeSession();
        }
    });

    cyclesInput.addEventListener('change', () => {
        getSafeCycleTarget();
        if (phase === 'ready' && !running) {
            cycleTarget = getSafeCycleTarget();
            updateUi();
        }
    });

    if (focusBtn) {
        focusBtn.addEventListener('click', () => {
            setFocusMode(!focusMode);
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && completionPopupEl && !completionPopupEl.hidden) {
            closeCompletionPopup();
            return;
        }
        if (event.key === 'Escape' && focusMode) {
            setFocusMode(false);
            if (focusBtn) focusBtn.focus();
        }
    });

    if (completionPopupCloseBtn) {
        completionPopupCloseBtn.addEventListener('click', closeCompletionPopup);
    }

    if (completionPopupBackdrop) {
        completionPopupBackdrop.addEventListener('click', closeCompletionPopup);
    }

    if (completionPopupRestartBtn) {
        completionPopupRestartBtn.addEventListener('click', () => {
            closeCompletionPopup();
            resetSession();
            startSession();
        });
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
        });
    }

    if (cards.length > 0) {
        cards[0].click();
    } else {
        statusEl.textContent = 'Aucun exercice actif disponible.';
        selected = null;
        setPhase('ready');
        setSessionState('Pret', 'ready');
        updateUi();
    }

    setBodyPhase('ready');
    setSessionState('Pret', 'ready');
    cycleTarget = getSafeCycleTarget();
    updateUi();
})();
