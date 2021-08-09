module.exports = {
  base: '/json-api-server/',
  title: 'json-api-server',
  description: 'A fully automated JSON:API server implementation in PHP.',
  evergreen: true,
  themeConfig: {
    search: false,
    nav: [
      { text: 'Guide', link: '/' }
    ],
    sidebar: [
      {
        title: 'Getting Started',
        collapsable: false,
        children: [
          '/',
          'install',
          'requests',
        ]
      },
      {
        title: 'Defining Resources',
        collapsable: false,
        children: [
          'adapters',
          'scopes',
          'attributes',
          'relationships',
          'visibility',
          'writing',
          'filtering',
          'sorting',
          'pagination',
          'meta',
        ]
      },
      {
        title: 'Endpoints',
        collapsable: false,
        children: [
          'list',
          'show',
          'create',
          'update',
          'delete',
        ]
      },
      {
        title: 'Advanced',
        collapsable: false,
        children: [
          'errors',
          'extensions',
          'laravel',
        ]
      }
    ],
    repo: 'tobyz/json-api-server',
    editLinks: true,
    docsDir: 'docs'
  }
}
