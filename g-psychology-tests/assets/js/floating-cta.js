/**
 * Floating CTA — psychology tests plugin
 * Injects a sticky phone-call button that is always visible.
 * SVG icon, no emoji.
 */
( function () {
    'use strict';

    const phone = window.GPT?.doctor_phone ?? '09030429138';
    const text  = window.GPT?.cta_text    ?? 'مشاوره با دکتر دوزنده';

    const phoneSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.6a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.77 3h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 10.91a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 18.42z"/></svg>`;

    function inject() {
        // Don't duplicate
        if ( document.getElementById( 'gpt-floating-cta' ) ) return;

        const cta = document.createElement( 'a' );
        cta.id          = 'gpt-floating-cta';
        cta.className   = 'gpt-floating-cta';
        cta.href        = `tel:+98${ phone.replace( /^0/, '' ) }`;
        cta.setAttribute( 'aria-label', `تماس با دکتر دوزنده — ${ phone }` );
        cta.innerHTML   = `
            ${ phoneSvg }
            <span>${ text }</span>
            <span class="gpt-cta-phone" dir="ltr">${ phone }</span>
        `;

        document.body.appendChild( cta );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', inject );
    } else {
        inject();
    }
} )();
