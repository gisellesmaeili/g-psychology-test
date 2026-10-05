/**
 * Admin — Quiz Manager
 * CRUD for quizzes, questions, options.
 * SortableJS for drag-and-drop reorder.
 * No emoji. SVG icons inline.
 */
( function () {
    'use strict';

    const AJAX  = window.GPT_ADMIN?.ajax_url ?? '/wp-admin/admin-ajax.php';
    const NONCE = window.GPT_ADMIN?.nonce    ?? '';

    const listEl   = document.getElementById( 'gpt-quiz-list'   );
    const editorEl = document.getElementById( 'gpt-quiz-editor' );

    if ( ! listEl ) return;

    let editingId = null;

    // ── SVG helpers ─────────────────────────────────────────────────────────
    const ICON = {
        edit:     `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`,
        trash:    `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>`,
        grip:     `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>`,
        plus:     `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>`,
        save:     `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>`,
        alert:    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    };

    // ── Load Quiz List ───────────────────────────────────────────────────────
    async function loadList() {
        listEl.innerHTML = `<div class="gpt-loading"><span class="gpt-admin-spinner"></span> در حال بارگذاری...</div>`;

        try {
            const { quizzes } = await restFetch( 'quizzes' );
            renderList( quizzes ?? [] );
        } catch {
            listEl.innerHTML = `<p class="gpt-admin-notice gpt-admin-notice-error">خطا در بارگذاری آزمون‌ها.</p>`;
        }
    }

    function renderList( quizzes ) {
        if ( ! quizzes.length ) {
            listEl.innerHTML = `<div class="gpt-list-empty">هنوز هیچ آزمونی ثبت نشده. اولین آزمون را اضافه کنید.</div>`;
            return;
        }

        listEl.innerHTML = quizzes.map( q => `
            <div class="gpt-quiz-item" data-id="${ q.id }">
                <span class="gpt-quiz-item-title">${ esc( q.title ) }</span>
                <span class="gpt-quiz-item-meta">
                    ${ ( q.components ?? [] ).length } مؤلفه
                </span>
                <div class="gpt-col-actions">
                    <button class="gpt-admin-btn gpt-admin-btn-icon gpt-btn-edit-quiz"
                        data-id="${ q.id }" aria-label="ویرایش آزمون ${ esc( q.title ) }">
                        ${ ICON.edit }
                    </button>
                    <button class="gpt-admin-btn gpt-admin-btn-icon gpt-admin-btn-danger gpt-btn-del-quiz"
                        data-id="${ q.id }" aria-label="حذف آزمون ${ esc( q.title ) }">
                        ${ ICON.trash }
                    </button>
                </div>
            </div>
        ` ).join( '' );

        listEl.querySelectorAll( '.gpt-btn-edit-quiz' ).forEach( btn =>
            btn.addEventListener( 'click', () => openEditor( parseInt( btn.dataset.id, 10 ) ) )
        );

        listEl.querySelectorAll( '.gpt-btn-del-quiz' ).forEach( btn =>
            btn.addEventListener( 'click', () => deleteQuiz( parseInt( btn.dataset.id, 10 ) ) )
        );
    }

    // ── Open Editor ──────────────────────────────────────────────────────────
    async function openEditor( id = 0 ) {
        editingId = id;
        editorEl.innerHTML = `<div class="gpt-loading"><span class="gpt-admin-spinner"></span> بارگذاری...</div>`;
        editorEl.style.display = 'block';
        editorEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );

        let quiz = null, questions = [];

        if ( id ) {
            try {
                const res  = await ajaxFetch( 'gpt_get_quiz', { id } );
                quiz       = res.quiz;
                questions  = res.questions ?? [];
            } catch {
                editorEl.innerHTML = `<p class="gpt-admin-notice gpt-admin-notice-error">خطا در بارگذاری آزمون.</p>`;
                return;
            }
        }

        renderEditor( quiz, questions );
    }

    function renderEditor( quiz, questions ) {
        editorEl.innerHTML = `
            <div class="gpt-editor-wrap">
                <h2 class="gpt-admin-title" style="font-size:18px;margin-bottom:20px;">
                    ${ editingId ? 'ویرایش آزمون' : 'افزودن آزمون جدید' }
                </h2>

                <div id="gpt-editor-notice"></div>

                <div class="gpt-editor-section">
                    <label class="gpt-editor-label" for="eq-title">عنوان آزمون *</label>
                    <input class="gpt-editor-input" id="eq-title" type="text"
                        value="${ esc( quiz?.title ?? '' ) }" placeholder="مثال: آزمون اضطراب بک" required>
                </div>

                <div class="gpt-editor-section">
                    <label class="gpt-editor-label" for="eq-desc">توضیحات</label>
                    <textarea class="gpt-editor-textarea" id="eq-desc" rows="3"
                        placeholder="توضیح کوتاهی درباره این آزمون...">${ esc( quiz?.description ?? '' ) }</textarea>
                </div>

                <div class="gpt-editor-section">
                    <label class="gpt-editor-label">مؤلفه‌های آزمون</label>
                    <div id="eq-components" class="gpt-components-list"></div>
                    <button class="gpt-admin-btn gpt-admin-btn-secondary" id="eq-add-comp" style="margin-top:8px;">
                        ${ ICON.plus } افزودن مؤلفه
                    </button>
                </div>

                <hr style="border:none;border-top:1px solid var(--gpt-border);margin:24px 0;">

                <div class="gpt-editor-section">
                    <label class="gpt-editor-label">سوالات</label>
                    <div id="eq-questions-list"></div>
                    <button class="gpt-admin-btn gpt-admin-btn-secondary" id="eq-add-q" style="margin-top:8px;">
                        ${ ICON.plus } افزودن سوال
                    </button>
                </div>

                <div style="display:flex;gap:12px;justify-content:flex-start;margin-top:24px;">
                    <button class="gpt-admin-btn gpt-admin-btn-primary" id="eq-save">
                        ${ ICON.save } ذخیره آزمون
                    </button>
                    <button class="gpt-admin-btn gpt-admin-btn-secondary" id="eq-cancel">بستن</button>
                </div>
            </div>
        `;

        // Populate components
        const compList  = editorEl.querySelector( '#eq-components' );
        const compData  = Array.isArray( quiz?.components_data ) ? quiz.components_data : [];
        compData.forEach( c => addComponentRow( compList, c ) );

        // Populate questions
        const qList = editorEl.querySelector( '#eq-questions-list' );
        questions.forEach( q => addQuestionRow( qList, q ) );

        // Init SortableJS on questions
        if ( window.Sortable && qList ) {
            Sortable.create( qList, {
                handle:    '.gpt-drag-handle',
                animation: 150,
                ghostClass:'gpt-sortable-ghost',
            } );
        }

        // Events
        editorEl.querySelector( '#eq-add-comp' ).addEventListener( 'click', () => addComponentRow( compList ) );
        editorEl.querySelector( '#eq-add-q'    ).addEventListener( 'click', () => addQuestionRow( qList ) );
        editorEl.querySelector( '#eq-save'     ).addEventListener( 'click', () => saveQuiz() );
        editorEl.querySelector( '#eq-cancel'   ).addEventListener( 'click', () => {
            editorEl.style.display = 'none';
            editorEl.innerHTML = '';
        } );
    }

    function addComponentRow( container, comp = {} ) {
        const row = document.createElement( 'div' );
        row.className = 'gpt-option-item';
        row.innerHTML = `
            <input class="gpt-editor-input comp-id"    type="hidden" value="${ esc( comp.id    ?? '' ) }">
            <input class="gpt-editor-input comp-label" type="text"   placeholder="نام مؤلفه" value="${ esc( comp.label ?? '' ) }" style="flex:2;">
            <input class="gpt-editor-input comp-color" type="color"  value="${ comp.color ?? '#910019' }" style="width:44px;padding:3px;">
            <input class="gpt-editor-input comp-max"   type="number" placeholder="حداکثر" min="1" value="${ comp.max_score ?? '' }" style="width:80px;">
            <button class="gpt-admin-btn gpt-admin-btn-icon gpt-admin-btn-danger" type="button" onclick="this.closest('.gpt-option-item').remove()" aria-label="حذف مؤلفه">
                ${ ICON.trash }
            </button>
        `;
        container.appendChild( row );
    }

    function addQuestionRow( container, q = {} ) {
        const qRow = document.createElement( 'div' );
        qRow.className = 'gpt-question-item';
        qRow.dataset.qid = q.id ?? '';

        // Build component options from editor components
        const compOpts = Array.from(
            document.querySelectorAll( '.comp-label' )
        ).map( el => {
            const val = el.value.trim();
            return val ? `<option value="${ esc( val ) }" ${ val === q.component ? 'selected' : '' }>${ esc( val ) }</option>` : '';
        } ).join( '' );

        qRow.innerHTML = `
            <div class="gpt-question-header" style="display:flex;gap:8px;align-items:center;">
                <span class="gpt-drag-handle" title="جابجایی">${ ICON.grip }</span>
                <textarea class="gpt-editor-textarea gpt-q-text" rows="2" style="flex:2;"
                    placeholder="متن سوال را بنویسید...">${ esc( q.question ?? '' ) }</textarea>
                <select class="gpt-editor-select opt-comp" style="width:120px;" aria-label="مؤلفه">
                    <option value="">مؤلفه</option>${ compOpts }
                </select>
                <button class="gpt-admin-btn gpt-admin-btn-icon gpt-admin-btn-danger" type="button"
                    onclick="this.closest('.gpt-question-item').remove()" aria-label="حذف سوال">
                    ${ ICON.trash }
                </button>
            </div>
            <div class="gpt-options-list"></div>
            <button class="gpt-admin-btn gpt-admin-btn-secondary gpt-add-opt" type="button" style="margin-top:8px;font-size:13px;">
                ${ ICON.plus } افزودن گزینه
            </button>
        `;

        const optsList = qRow.querySelector( '.gpt-options-list' );
        ( q.options ?? [] ).forEach( opt => addOptionRow( optsList, opt ) );

        qRow.querySelector( '.gpt-add-opt' ).addEventListener( 'click', () => addOptionRow( optsList ) );
        container.appendChild( qRow );
    }

    function addOptionRow( container, opt = {} ) {
        const row = document.createElement( 'div' );
        row.className   = 'gpt-option-item';
        row.dataset.oid = opt.id ?? '';

        row.innerHTML = `
            <span class="gpt-drag-handle">${ ICON.grip }</span>
            <input class="gpt-editor-input opt-label" type="text" placeholder="متن گزینه" value="${ esc( opt.label ?? '' ) }" style="flex:2;">
            <input class="gpt-editor-input opt-score" type="number" placeholder="امتیاز" value="${ opt.score ?? 1 }" style="width:70px;" min="0">
            <button class="gpt-admin-btn gpt-admin-btn-icon gpt-admin-btn-danger" type="button"
                onclick="this.closest('.gpt-option-item').remove()" aria-label="حذف گزینه">
                ${ ICON.trash }
            </button>
        `;
        container.appendChild( row );
    }

    // ── Save ─────────────────────────────────────────────────────────────────
    async function saveQuiz() {
        const noticeEl = editorEl.querySelector( '#gpt-editor-notice' );
        const saveBtn  = editorEl.querySelector( '#eq-save' );

        const title = editorEl.querySelector( '#eq-title' ).value.trim();
        if ( ! title ) {
            noticeEl.innerHTML = `<p class="gpt-admin-notice gpt-admin-notice-error">${ ICON.alert } عنوان آزمون الزامی است.</p>`;
            return;
        }

        // Collect components
        const components = Array.from( editorEl.querySelectorAll( '#eq-components .gpt-option-item' ) ).map( ( row, i ) => ( {
            id:        row.querySelector( '.comp-id'    )?.value.trim() || `comp_${ i }`,
            label:     row.querySelector( '.comp-label' )?.value.trim(),
            color:     row.querySelector( '.comp-color' )?.value,
            max_score: parseInt( row.querySelector( '.comp-max' )?.value, 10 ) || 0,
        } ) ).filter( c => c.label );

        // Collect questions
        const questions = Array.from( editorEl.querySelectorAll( '.gpt-question-item' ) ).map( ( qRow, qi ) => {
            const qid = parseInt( qRow.dataset.qid, 10 ) || 0;
            const options = Array.from( qRow.querySelectorAll( '.gpt-options-list .gpt-option-item' ) ).map( ( oRow, oi ) => ( {
                id:        parseInt( oRow.dataset.oid, 10 ) || 0,
                label:     oRow.querySelector( '.opt-label' )?.value.trim(),
                score:     ( () => { const s = parseInt( oRow.querySelector( '.opt-score' )?.value, 10 ); return isNaN(s) ? 1 : s; } )(),
                sort_order:oi,
            } ) ).filter( o => o.label );

            return {
                id:         qid,
                question:   qRow.querySelector( '.gpt-q-text' )?.value.trim(),
                component:  qRow.querySelector( '.opt-comp' )?.value,
                sort_order: qi,
                options,
            };
        } ).filter( q => q.question );

        saveBtn.disabled = true;
        saveBtn.innerHTML = `<span class="gpt-admin-spinner" style="width:14px;height:14px;border-width:2px;"></span> در حال ذخیره...`;

        const form = new FormData();
        form.append( 'action',      'gpt_save_quiz' );
        form.append( 'nonce',       NONCE );
        form.append( 'id',          editingId ?? 0 );
        form.append( 'title',       title );
        form.append( 'description', editorEl.querySelector( '#eq-desc' )?.value ?? '' );
        form.append( 'components',  JSON.stringify( components ) );
        form.append( 'questions',   JSON.stringify( questions ) );

        try {
            const res = await ajaxPost( form );
            noticeEl.innerHTML = `<p class="gpt-admin-notice gpt-admin-notice-success">آزمون با موفقیت ذخیره شد.</p>`;
            editingId = res.quiz_id;
            loadList();
        } catch ( e ) {
            noticeEl.innerHTML = `<p class="gpt-admin-notice gpt-admin-notice-error">${ ICON.alert } ${ e.message }</p>`;
        } finally {
            saveBtn.disabled  = false;
            saveBtn.innerHTML = `${ ICON.save } ذخیره آزمون`;
        }
    }

    // ── Delete Quiz ──────────────────────────────────────────────────────────
    async function deleteQuiz( id ) {
        if ( ! confirm( 'این آزمون و تمام داده‌های مرتبط با آن حذف خواهند شد. آیا ادامه می‌دهید؟' ) ) return;

        const form = new FormData();
        form.append( 'action', 'gpt_delete_quiz' );
        form.append( 'nonce',  NONCE );
        form.append( 'id',     id );

        try {
            await ajaxPost( form );
            loadList();
        } catch ( e ) {
            alert( 'خطا: ' + e.message );
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    async function restFetch( endpoint ) {
        const REST  = window.GPT_ADMIN?.rest_url ?? '';
        const WNONCE= window.GPT_ADMIN?.wp_nonce ?? '';
        const res   = await fetch( REST + endpoint, {
            headers: { 'X-WP-Nonce': WNONCE },
        } );
        const json = await res.json();
        if ( ! res.ok ) throw new Error( json?.message ?? 'خطا' );
        return json;
    }

    async function ajaxFetch( action, params = {} ) {
        const form = new FormData();
        form.append( 'action', action );
        form.append( 'nonce',  NONCE );
        Object.entries( params ).forEach( ( [ k, v ] ) => form.append( k, v ) );
        return ajaxPost( form );
    }

    async function ajaxPost( formData ) {
        const res  = await fetch( AJAX, { method: 'POST', body: formData } );
        const json = await res.json();
        if ( ! json.success ) throw new Error( json?.data?.message ?? 'خطا' );
        return json.data;
    }

    function esc( str ) {
        const d = document.createElement( 'div' );
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    // ── New Quiz Button ───────────────────────────────────────────────────────
    document.getElementById( 'gpt-new-quiz-btn' )?.addEventListener( 'click', () => {
        editingId = 0;
        openEditor( 0 );
    } );

    // ── Init ─────────────────────────────────────────────────────────────────
    loadList();
} )();
