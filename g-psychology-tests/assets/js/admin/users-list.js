/**
 * Admin — Users List
 * Debounced live search · flex rows · pagination · delete
 */
( function () {
    'use strict';

    const REST     = window.GPT_ADMIN?.rest_url  ?? '/wp-json/gpt/v1/';
    const WP_NONCE = window.GPT_ADMIN?.wp_nonce  ?? '';

    let page      = 1;
    let perPage   = 20;
    let lastSearch= '';
    let lastQuiz  = '';
    let debounceT;

    const body    = document.getElementById( 'gpt-list-body'   );
    const pager   = document.getElementById( 'gpt-pagination'  );
    const search  = document.getElementById( 'gpt-search-input');
    const quizSel = document.getElementById( 'gpt-quiz-filter' );
    const csvBtn  = document.getElementById( 'gpt-csv-btn'     );

    if ( ! body ) return;

    // ── Events ──────────────────────────────────────────────────────────────
    search?.addEventListener( 'input', () => {
        clearTimeout( debounceT );
        debounceT = setTimeout( () => { page = 1; load(); }, 300 );
    } );

    quizSel?.addEventListener( 'change', () => { page = 1; load(); } );

    // CSV URL — sync query params
    csvBtn?.addEventListener( 'click', ( e ) => {
        e.preventDefault();
        const url = new URL( csvBtn.href );
        if ( search?.value ) url.searchParams.set( 'search',  search.value );
        if ( quizSel?.value ) url.searchParams.set( 'quiz_id', quizSel.value );
        window.location.href = url.toString();
    } );

    // ── Load ────────────────────────────────────────────────────────────────
    async function load() {
        lastSearch = search?.value.trim() ?? '';
        lastQuiz   = quizSel?.value ?? '';

        body.innerHTML = `<div class="gpt-loading" role="status"><span class="gpt-admin-spinner"></span> در حال بارگذاری...</div>`;

        const params = new URLSearchParams( { page, per_page: perPage } );
        if ( lastSearch ) params.set( 'search',  lastSearch );
        if ( lastQuiz   ) params.set( 'quiz_id', lastQuiz   );

        try {
            const data = await fetchJson( `admin/respondents?${ params }` );
            renderRows( data.respondents ?? [] );
            renderPager( data );
        } catch {
            body.innerHTML = `<p class="gpt-admin-notice gpt-admin-notice-error">خطا در بارگذاری. صفحه را رفرش کنید.</p>`;
        }
    }

    // ── Render Rows ─────────────────────────────────────────────────────────
    function renderRows( rows ) {
        if ( ! rows.length ) {
            body.innerHTML = `<div class="gpt-list-empty">هیچ کاربری یافت نشد.</div>`;
            return;
        }

        body.innerHTML = rows.map( r => {
            const score  = r.scores?.total ?? '—';
            const date   = formatDate( r.created_at );
            const viewUrl= `?page=gpt-users&action=view&id=${ r.id }`;

            return `
                <div class="gpt-row gpt-user-card" role="listitem" data-id="${ r.id }">
                    <span class="gpt-col-name">
                        <strong>${ esc( r.name || '—' ) }</strong>
                    </span>
                    <span class="gpt-col-phone" dir="ltr">${ esc( r.phone ) }</span>
                    <span class="gpt-col-quiz">${ esc( r.quiz_title ) }</span>
                    <span class="gpt-col-score">${ score }</span>
                    <span class="gpt-col-date">${ date }</span>
                    <span class="gpt-col-actions">
                        <button class="gpt-admin-btn gpt-admin-btn-icon gpt-btn-delete"
                            data-id="${ r.id }"
                            aria-label="حذف ${ esc( r.name || r.phone ) }">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </button>
                    </span>
                </div>
            `;
        } ).join( '' );

        // Delete handlers
        body.querySelectorAll( '.gpt-btn-delete' ).forEach( btn => {
            btn.addEventListener( 'click', () => deleteRespondent( parseInt( btn.dataset.id, 10 ) ) );
        } );
    }

    // ── Pagination ──────────────────────────────────────────────────────────
    function renderPager( { total, pages, page: p } ) {
        if ( pages <= 1 ) { pager.innerHTML = ''; return; }

        let html = `<div class="gpt-pager">`;
        html += `<span class="gpt-pager-info">مجموع: ${ total } کاربر</span>`;

        if ( p > 1 ) {
            html += `<button class="gpt-admin-btn gpt-admin-btn-page" data-page="${ p - 1 }">قبلی</button>`;
        }
        html += `<span class="gpt-pager-current">صفحه ${ p } از ${ pages }</span>`;
        if ( p < pages ) {
            html += `<button class="gpt-admin-btn gpt-admin-btn-page" data-page="${ p + 1 }">بعدی</button>`;
        }
        html += '</div>';

        pager.innerHTML = html;
        pager.querySelectorAll( '[data-page]' ).forEach( btn => {
            btn.addEventListener( 'click', () => {
                page = parseInt( btn.dataset.page, 10 );
                load();
            } );
        } );
    }

    // ── Delete ──────────────────────────────────────────────────────────────
    async function deleteRespondent( id ) {
        if ( ! confirm( 'آیا از حذف این کاربر اطمینان دارید؟' ) ) return;

        try {
            await fetchJson( `admin/respondents/${ id }`, { method: 'DELETE' } );
            load();
        } catch {
            alert( 'خطا در حذف کاربر.' );
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    async function fetchJson( endpoint, opts = {} ) {
        const res = await fetch( REST + endpoint, {
            headers: { 'X-WP-Nonce': WP_NONCE, 'Content-Type': 'application/json' },
            ...opts,
        } );
        const json = await res.json();
        if ( ! res.ok ) throw new Error( json?.message ?? 'خطا' );
        return json;
    }

    function esc( str ) {
        const d = document.createElement( 'div' );
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function formatDate( dt ) {
        if ( ! dt ) return '—';
        return new Date( dt ).toLocaleDateString( 'fa-IR', {
            year: 'numeric', month: 'long', day: 'numeric'
        } );
    }

    // ── Bootstrap ────────────────────────────────────────────────────────────
    load();
} )();
