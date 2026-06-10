// Read the publicPath provided by the user in config.js, defaulting to root "/"
const runtimePublicPath = (window.CM3_CONFIG && window.CM3_CONFIG.publicPath) || '/';

// Force Webpack's internal runtime engine to use the user's variable on the fly
__webpack_public_path__ = runtimePublicPath;
