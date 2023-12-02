import { defineConfig } from 'vitepress';

// https://vitepress.dev/reference/site-config
export default defineConfig({
    base: '/json-api-server/',
    title: 'json-api-server',
    description: 'A fully automated JSON:API server implementation in PHP.',
    themeConfig: {
        // https://vitepress.dev/reference/default-theme-config
        sidebar: [
            {
                text: 'Getting Started',
                items: [
                    { text: 'Introduction', link: '/' },
                    { text: 'Installation', link: '/install' },
                    { text: 'Handling Requests', link: '/requests' },
                    { text: 'Laravel Integration', link: '/laravel' },
                ],
            },
            {
                text: 'Resources',
                items: [
                    { text: 'Defining Resources', link: '/resources' },
                    { text: 'Defining Fields', link: '/fields' },
                    { text: 'Attributes', link: '/attributes' },
                    { text: 'Relationships', link: '/relationships' },
                ],
            },
            {
                text: 'Endpoints',
                items: [
                    { text: 'Index', link: '/list' },
                    { text: 'Create', link: '/create' },
                    { text: 'Show', link: '/show' },
                    { text: 'Update', link: '/update' },
                    { text: 'Delete', link: '/delete' },
                ],
            },
            {
                text: 'Advanced',
                items: [
                    { text: 'Context', link: '/context' },
                    { text: 'Deferred Values', link: '/deferred' },
                    { text: 'Error Handling', link: '/errors' },
                    { text: 'Extensions', link: '/extensions' },
                    {
                        text: 'Heterogeneous Collections',
                        link: '/collections',
                    },
                    { text: 'OpenAPI Definitions', link: '/openapi' },
                ],
            },
        ],
        outline: [2, 3],
        socialLinks: [
            {
                icon: 'github',
                link: 'https://github.com/tobyzerner/json-api-server',
            },
        ],
        search: { provider: 'local' },
        editLink: {
            pattern:
                'https://github.com/tobyzerner/json-api-server/edit/main/docs/:path',
        },
    },
});
