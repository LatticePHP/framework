import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'LatticePHP',
  description: 'A modular PHP framework with NestJS-style architecture and native durable workflow orchestration',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'API', link: '/api/' },
      { text: 'Examples', link: '/examples/crm' },
      {
        text: 'Ecosystem',
        items: [
          { text: 'Starter API Kit', link: 'https://github.com/latticephp/starter-api' },
          { text: 'CRM Example', link: 'https://github.com/latticephp/lattice/tree/main/examples/crm' },
        ]
      }
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/getting-started' },
            { text: 'Why LatticePHP', link: '/guide/why-latticephp' },
          ]
        },
        {
          text: 'Core Concepts',
          items: [
            { text: 'Architecture', link: '/guide/architecture' },
            { text: 'Modules', link: '/guide/modules' },
            { text: 'Pipeline', link: '/guide/pipeline' },
          ]
        },
        {
          text: 'Building APIs',
          items: [
            { text: 'HTTP & API', link: '/guide/http-api' },
            { text: 'Database', link: '/guide/database' },
            { text: 'Testing', link: '/guide/testing' },
          ]
        },
        {
          text: 'Auth & Security',
          items: [
            { text: 'Authentication', link: '/guide/auth' },
            { text: 'Security', link: '/guide/security' },
          ]
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Workflows', link: '/guide/workflows' },
            { text: 'Microservices', link: '/guide/microservices' },
            { text: 'Observability', link: '/guide/observability' },
          ]
        },
        {
          text: 'Operations',
          items: [
            { text: 'Runtime', link: '/guide/runtime' },
            { text: 'Package Authoring', link: '/guide/package-authoring' },
          ]
        },
        {
          text: 'Migration',
          items: [
            { text: 'From Laravel', link: '/guide/migration-from-laravel' },
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/latticephp/lattice' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 LatticePHP'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/latticephp/lattice/edit/main/website/:path'
    },
  },

  markdown: {
    theme: {
      light: 'github-light',
      dark: 'github-dark'
    },
    lineNumbers: true
  }
})
