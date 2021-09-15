# json-api-server

[![Pre Release](https://img.shields.io/packagist/vpre/tobyz/json-api-server.svg?style=flat)](https://github.com/tobyzerner/json-api-server/releases)
[![License](https://img.shields.io/packagist/l/tobyz/json-api-server.svg?style=flat)](https://packagist.org/packages/tobyz/json-api-server)

json-api-server is a [JSON:API](http://jsonapi.org) server implementation in PHP.

It allows you to define your API's schema, and then use an [adapter](adapters.md) to connect it to your application's database layer. You don't have to worry about any of the server boilerplate, routing, query parameters, or JSON:API document formatting.

Based on your schema definition, the package will serve a **complete JSON:API that conforms to the [spec](https://jsonapi.org/format/)**, including support for:

- **Showing** individual resources (`GET /api/articles/1`)
- **Listing** resource collections (`GET /api/articles`)
- **Sorting**, **filtering**, **pagination**, and **sparse fieldsets**
- **Compound documents** with inclusion of related resources
- **Creating** resources (`POST /api/articles`)
- **Updating** resources (`PATCH /api/articles/1`)
- **Deleting** resources (`DELETE /api/articles/1`)
- **Error handling**

The schema definition is extremely powerful and lets you easily apply [permissions](visibility.md), [transformations](writing.md#transformers), [validation](writing.md#validation), and custom [filtering](filtering.md) and [sorting](sorting.md) logic to build a fully functional API with ease.

## Documentation

[Read the documentation](https://tobyzerner.github.io/json-api-server)

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](LICENSE)
