# gridx-extensions-indexer

Broccoli plugin which indexes gridx extensions installed using npm for the GridX Console


## Compatibility

* Node.js v14 or above


## Installation

```
yarn add gridx-extensions-indexer
```


## Usage

```js
# ember-cli-build.js
/**
 * After let app = new EmberApp(defaults);
 * initialize the gridx extensions indexer
 */
const extensions = new GridXExtensionsIndexer();

/**
 * Add to tree
 */
return app.toTree([extensions]);
```


## Contributing

See the [Contributing](CONTRIBUTING.md) guide for details.


## License

This project is licensed under the [MIT License](LICENSE.md).
