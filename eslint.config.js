import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    files: ['assets/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',          // IIFE dosyaları — module değil
      globals: {
        // Browser globals
        window:         'readonly',
        document:       'readonly',
        navigator:      'readonly',
        location:       'readonly',
        history:        'readonly',
        console:        'readonly',
        alert:          'readonly',
        confirm:        'readonly',
        prompt:         'readonly',
        setTimeout:     'readonly',
        setInterval:    'readonly',
        clearTimeout:   'readonly',
        clearInterval:  'readonly',
        requestAnimationFrame: 'readonly',
        cancelAnimationFrame:  'readonly',
        fetch:          'readonly',
        URL:            'readonly',
        URLSearchParams:'readonly',
        FormData:       'readonly',
        Image:          'readonly',
        CSS:            'readonly',
        MutationObserver: 'readonly',
        IntersectionObserver: 'readonly',
        ResizeObserver: 'readonly',
        AbortController:'readonly',
        CustomEvent:    'readonly',
        Event:          'readonly',
        EventSource:    'readonly',
        Node:           'readonly',
        NodeList:       'readonly',
        HTMLElement:    'readonly',
        Element:        'readonly',
        localStorage:   'readonly',
        sessionStorage: 'readonly',
        performance:    'readonly',
        matchMedia:     'readonly',
        // 3rd-party globals
        L:              'readonly',  // Leaflet
      },
    },
    rules: {
      // Hata sınıfı — gerçekten kırık kod
      'no-undef':            'error',
      'no-unreachable':      'error',
      'no-empty':            ['error', { allowEmptyCatch: true }],
      'no-duplicate-case':   'error',
      'use-isnan':           'error',
      'no-self-assign':      'error',

      // Uyarı sınıfı — kalite iyileştirme
      'no-unused-vars':      ['warn', { vars: 'all', args: 'none', ignoreRestSiblings: true }],
      'eqeqeq':              ['warn', 'always', { null: 'ignore' }],
      'no-console':          'warn',
      'no-alert':            'warn',

      // Kapalı — projenin mevcut stilini koruyoruz
      'no-var':              'off',   // eski dosyalar var kullanıyor
      'prefer-const':        'off',
      'prefer-arrow-callback':'off',
    },
  },
  {
    // .min.js dosyaları lint edilmez
    ignores: ['assets/js/*.min.js', 'node_modules/**', 'vendor/**', 'assets/vendor/**'],
  },
];
