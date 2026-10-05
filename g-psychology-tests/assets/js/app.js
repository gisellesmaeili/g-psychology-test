/**
 * G-Psychology Tests — Frontend App
 * Vanilla JS ES Module · RTL · No framework
 */
( function () {
    'use strict';

    // ── Constants ───────────────────────────────────────────────────────────
    const REST_URL = window.GPT?.rest_url  ?? '/wp-json/gpt/v1/';
    const NONCE    = window.GPT?.nonce     ?? '';

    // ── State ───────────────────────────────────────────────────────────────
    let state = {
        quiz:       null,
        questions:  [],
        answers:    {},     // { question_id: option_id }
        current:    0,      // current question index
        step:       'start', // 'start' | 'questions' | 'user-info' | 'results'
        token:      null,
        results:    null,
    };

    // ── Init ────────────────────────────────────────────────────────────────
    document.querySelectorAll( '[data-gpt-quiz]' ).forEach( init );

    async function init( wrap ) {
        const quizId = parseInt( wrap.dataset.gptQuiz, 10 );
        if ( ! quizId ) return;

        wrap.innerHTML = tpl_loading();

        try {
            const data = await apiFetch( `quiz/${ quizId }` );
            state.quiz      = data.quiz;
            state.questions = data.questions ?? [];
            render( wrap );
        } catch ( e ) {
            wrap.innerHTML = `<p class="gpt-notice gpt-notice-error">خطا در بارگذاری آزمون.</p>`;
        }
    }

    // ── Render ──────────────────────────────────────────────────────────────
    function render( wrap ) {
        const { step } = state;

        if ( step === 'start' )     { renderStart( wrap ); return; }
        if ( step === 'questions' ) { renderQuestion( wrap ); return; }
        if ( step === 'user-info' ) { renderUserInfo( wrap ); return; }
        if ( step === 'results' )   { renderResults( wrap ); return; }
    }

    // ── Screen: Start ───────────────────────────────────────────────────────
    function renderStart( wrap ) {
        const q = state.quiz;
        wrap.innerHTML = `
            <div class="gpt-screen gpt-start-screen">
                <div class="gpt-start-icon">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/><path d="M12 8v4l3 3"/></svg>
                </div>
                <h2 class="gpt-quiz-title">${ esc( q.title ) }</h2>
                ${ q.description ? `<p class="gpt-quiz-desc">${ esc( q.description ) }</p>` : '' }
                ${ q.instructions ? `<p class="gpt-quiz-desc">${ esc( q.instructions ) }</p>` : '' }
                <p style="color:var(--color-text-muted);font-size:14px;">${ state.questions.length } سوال</p>
                <button class="gpt-btn gpt-btn-primary" id="gpt-start-btn" style="min-width:200px;">
                    شروع آزمون
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
            </div>
        `;

        wrap.querySelector( '#gpt-start-btn' ).addEventListener( 'click', () => {
            state.step    = 'questions';
            state.current = 0;
            state.answers = {};
            render( wrap );
        } );
    }

    // ── Screen: Question ────────────────────────────────────────────────────
    function renderQuestion( wrap ) {
        const { questions, current, answers } = state;
        const q     = questions[ current ];
        const total = questions.length;
        const pct   = Math.round( ( current / total ) * 100 );
        const selectedOpt = answers[ q.id ];

        wrap.innerHTML = `
            <div class="gpt-screen">
                ${ tpl_progress( current + 1, total, pct ) }

                <div class="gpt-question-card" role="group" aria-labelledby="gpt-q-text">
                    <p class="gpt-question-text" id="gpt-q-text">${ esc( q.question ) }</p>
                    <ul class="gpt-options" role="radiogroup" aria-label="گزینه‌های پاسخ">
                        ${ q.options.map( opt => `
                            <li class="gpt-option${ selectedOpt == opt.id ? ' is-selected' : '' }"
                                data-opt="${ opt.id }"
                                role="radio"
                                aria-checked="${ selectedOpt == opt.id ? 'true' : 'false' }"
                                tabindex="0">
                                <span class="gpt-option-radio" aria-hidden="true"></span>
                                <span class="gpt-option-label">${ esc( opt.label ) }</span>
                            </li>
                        ` ).join( '' ) }
                    </ul>
                </div>

                <nav class="gpt-nav" aria-label="ناوبری آزمون">
                    <button class="gpt-btn gpt-btn-secondary" id="gpt-prev"
                        ${ current === 0 ? 'disabled' : '' }>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                        قبلی
                    </button>
                    <span style="color:var(--color-text-muted);font-size:14px;" aria-live="polite">
                        ${ current + 1 } از ${ total }
                    </span>
                    <button class="gpt-btn gpt-btn-primary" id="gpt-next"
                        ${ ! selectedOpt ? 'disabled' : '' }>
                        ${ current === total - 1 ? 'مشاهده نتایج' : 'بعدی' }
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                </nav>
            </div>
        `;

        // Option click / keyboard
        wrap.querySelectorAll( '.gpt-option' ).forEach( el => {
            const select = () => {
                const optId = parseInt( el.dataset.opt, 10 );
                state.answers[ q.id ] = optId;

                wrap.querySelectorAll( '.gpt-option' ).forEach( o => {
                    o.classList.remove( 'is-selected' );
                    o.setAttribute( 'aria-checked', 'false' );
                } );
                el.classList.add( 'is-selected' );
                el.setAttribute( 'aria-checked', 'true' );

                // Enable next
                const nextBtn = wrap.querySelector( '#gpt-next' );
                if ( nextBtn ) nextBtn.disabled = false;
            };

            el.addEventListener( 'click', select );
            el.addEventListener( 'keydown', e => {
                if ( e.key === ' ' || e.key === 'Enter' ) {
                    e.preventDefault();
                    select();
                }
            } );
        } );

        wrap.querySelector( '#gpt-prev' )?.addEventListener( 'click', () => {
            state.current--;
            render( wrap );
            focusTop( wrap );
        } );

        wrap.querySelector( '#gpt-next' )?.addEventListener( 'click', () => {
            if ( current < total - 1 ) {
                state.current++;
                render( wrap );
                focusTop( wrap );
            } else {
                state.step = 'user-info';
                render( wrap );
            }
        } );
    }

    // ── Screen: User Info ───────────────────────────────────────────────────
    function renderUserInfo( wrap ) {
        wrap.innerHTML = `
            <div class="gpt-screen">
                <div class="gpt-user-form-wrap">
                    <h2 class="gpt-form-heading">اطلاعات شما</h2>
                    <p class="gpt-form-sub">برای مشاهده نتایج آزمون، لطفاً اطلاعات زیر را وارد کنید.</p>

                    <form id="gpt-user-form" novalidate>
                        <div class="gpt-field" id="field-name">
                            <label class="gpt-label" for="gpt-name">
                                نام و نام خانوادگی
                                <span class="gpt-req" aria-hidden="true">*</span>
                            </label>
                            <input class="gpt-input" id="gpt-name" name="name" type="text"
                                autocomplete="name" placeholder="مثال: علی احمدی" required>
                            <span class="gpt-field-error" role="alert">لطفاً نام خود را وارد کنید.</span>
                        </div>

                        <div class="gpt-field" id="field-phone">
                            <label class="gpt-label" for="gpt-phone">
                                شماره تماس
                                <span class="gpt-req" aria-hidden="true">*</span>
                            </label>
                            <input class="gpt-input" id="gpt-phone" name="phone" type="tel"
                                autocomplete="tel" placeholder="09xxxxxxxxx"
                                pattern="09[0-9]{9}" maxlength="11" required
                                style="direction:ltr;text-align:right;">
                            <span class="gpt-field-error" role="alert">شماره تلفن معتبر وارد کنید (09xxxxxxxxx).</span>
                        </div>

                        <div class="gpt-field-row">
                            <div class="gpt-field">
                                <label class="gpt-label" for="gpt-age">سن</label>
                                <input class="gpt-input" id="gpt-age" name="age" type="number"
                                    min="10" max="100" placeholder="مثلاً ۳۰">
                            </div>
                            <div class="gpt-field">
                                <label class="gpt-label" for="gpt-gender">جنسیت</label>
                                <select class="gpt-select" id="gpt-gender" name="gender">
                                    <option value="">انتخاب کنید</option>
                                    <option value="female">زن</option>
                                    <option value="male">مرد</option>
                                    <option value="other">سایر</option>
                                </select>
                            </div>
                        </div>

                        <div id="gpt-form-error" class="gpt-notice gpt-notice-error" style="display:none;" role="alert"></div>

                        <button class="gpt-btn gpt-btn-primary" type="submit" id="gpt-submit-btn"
                            style="width:100%;margin-top:8px;">
                            مشاهده نتایج آزمون
                        </button>
                    </form>
                </div>
            </div>
        `;

        wrap.querySelector( '#gpt-user-form' ).addEventListener( 'submit', async e => {
            e.preventDefault();
            await handleSubmit( wrap );
        } );
    }

    async function handleSubmit( wrap ) {
        const form    = wrap.querySelector( '#gpt-user-form' );
        const btn     = wrap.querySelector( '#gpt-submit-btn' );
        const errBox  = wrap.querySelector( '#gpt-form-error' );

        const name  = form.querySelector( '#gpt-name'   ).value.trim();
        const phone = form.querySelector( '#gpt-phone'  ).value.trim();
        const age   = parseInt( form.querySelector( '#gpt-age' ).value, 10 ) || null;
        const gender= form.querySelector( '#gpt-gender' ).value;

        // Validate
        let hasError = false;

        const setError = ( fieldId, show ) => {
            const f = wrap.querySelector( `#field-${ fieldId }` );
            if ( ! f ) return;
            f.classList.toggle( 'has-error', show );
            if ( show ) hasError = true;
        };

        setError( 'name',  ! name );
        setError( 'phone', ! /^09[0-9]{9}$/.test( phone ) );

        if ( hasError ) return;

        // Submit
        btn.disabled = true;
        btn.innerHTML = '<span class="gpt-spinner" aria-hidden="true"></span> در حال ارسال...';
        errBox.style.display = 'none';

        try {
            const data = await apiFetch( 'submit', {
                method: 'POST',
                body: JSON.stringify( {
                    quiz_id: state.quiz.id,
                    answers: state.answers,
                    user:    { name, phone, age, gender },
                } ),
            } );

            state.token   = data.token;
            state.results = data;
            state.step    = 'results';
            render( wrap );
        } catch ( err ) {
            errBox.textContent  = err.message || 'خطا در ارسال اطلاعات. لطفاً دوباره امتحان کنید.';
            errBox.style.display= 'block';
            btn.disabled        = false;
            btn.textContent     = 'مشاهده نتایج آزمون';
        }
    }

    // ── Screen: Results ─────────────────────────────────────────────────────
    async function renderResults( wrap ) {
        const scores     = state.results?.scores ?? {};
        const components = state.quiz?.components_data ?? [];

        wrap.innerHTML = `
            <div class="gpt-screen gpt-results-wrap">
                <h2 class="gpt-results-heading">نتایج آزمون شما</h2>
                <p class="gpt-results-sub">
                    امتیاز کل: <strong>${ scores.total ?? 0 } از ${ scores.max ?? 0 }</strong>
                </p>

                <div class="gpt-charts-grid" id="gpt-charts-grid"></div>

                <div style="text-align:center;margin-top:16px;">
                    <a href="tel:+98${ ( window.GPT?.doctor_phone ?? '09030429138' ).replace(/^0/, '') }"
                       class="gpt-btn gpt-btn-primary" style="display:inline-flex;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.6a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.77 3h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 10.91a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 18.42z"/></svg>
                        مشاوره با دکتر دوزنده
                    </a>
                </div>
            </div>
        `;

        // Lazy-load Chart.js then render charts
        await loadChartJs();
        renderCharts( wrap, components, scores );
    }

    function renderCharts( wrap, components, scores ) {
        const grid = wrap.querySelector( '#gpt-charts-grid' );
        if ( ! grid || ! components.length ) return;

        const chartColors = [
            '#910019','#c0395a','#d97706','#059669','#5a403f','#8e706e',
        ];

        components.forEach( ( comp, idx ) => {
            const pct   = scores.percentages?.[ comp.id ] ?? 0;
            const color = comp.color || chartColors[ idx % chartColors.length ];
            const label = interp( pct );
            const canvasId = `gpt-chart-${ comp.id }`;

            const card = document.createElement( 'div' );
            card.className = 'gpt-chart-card';
            card.innerHTML = `
                <div class="gpt-chart-canvas-wrap">
                    <canvas id="${ canvasId }" role="img" aria-label="${ esc( comp.label ) }: ${ pct }٪"></canvas>
                    <div class="gpt-chart-center-text" aria-hidden="true">
                        <span class="gpt-chart-pct">${ pct }٪</span>
                        <span class="gpt-chart-label-text">امتیاز</span>
                    </div>
                </div>
                <span class="gpt-chart-name">${ esc( comp.label ) }</span>
                <span class="gpt-chart-interp" style="background:${ color }22;color:${ color };">${ label }</span>
            `;
            grid.appendChild( card );

            new Chart( document.getElementById( canvasId ), {
                type: 'doughnut',
                data: {
                    datasets: [ {
                        data:            [ pct, 100 - pct ],
                        backgroundColor: [ color, '#f0e8e8' ],
                        borderWidth:     0,
                        borderRadius:    6,
                    } ],
                },
                options: {
                    cutout:  '72%',
                    plugins: {
                        legend:  { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ ctx.parsed }٪`,
                            },
                        },
                    },
                    animation: { duration: 900 },
                    responsive: false,
                },
            } );
        } );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    function tpl_loading() {
        return `<div class="gpt-loading" role="status" aria-live="polite">
            <span class="gpt-spinner" aria-hidden="true"></span>
            در حال بارگذاری...
        </div>`;
    }

    function tpl_progress( current, total, pct ) {
        return `
        <div class="gpt-progress-wrap" role="progressbar" aria-valuenow="${ current }" aria-valuemin="1" aria-valuemax="${ total }">
            <div class="gpt-progress-label">
                <span>سوال ${ current } از ${ total }</span>
                <span>${ pct }٪</span>
            </div>
            <div class="gpt-progress-track">
                <div class="gpt-progress-fill" style="width:${ pct }%"></div>
            </div>
        </div>`;
    }

    function interp( pct ) {
        if ( pct >= 75 ) return 'بالا';
        if ( pct >= 50 ) return 'متوسط';
        if ( pct >= 25 ) return 'پایین';
        return 'خیلی پایین';
    }

    function esc( str ) {
        const d = document.createElement( 'div' );
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function focusTop( wrap ) {
        const el = wrap.querySelector( '.gpt-question-card, .gpt-user-form-wrap, .gpt-results-wrap' );
        el?.focus();
    }

    async function apiFetch( endpoint, options = {} ) {
        const res = await fetch( REST_URL + endpoint, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   NONCE,
            },
            ...options,
        } );
        const json = await res.json();
        if ( ! res.ok ) {
            throw new Error( json?.message ?? 'خطای سرور' );
        }
        return json;
    }

    async function loadChartJs() {
        if ( window.Chart ) return;
        await new Promise( ( resolve, reject ) => {
            const s   = document.createElement( 'script' );
            s.src     = window.GPT?.chartjs_url ?? '';
            s.onload  = resolve;
            s.onerror = reject;
            document.head.appendChild( s );
        } );
    }

} )();
