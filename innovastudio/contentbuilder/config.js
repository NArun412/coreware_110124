//PLUGINS
// _cb.settings.plugins = ['preview','wordcount','buttoneditor', 'symbols']; // old way
_cb.settings.plugins = [
    { name: 'preview', showInMainToolbar: true, showInElementToolbar: true },
    { name: 'wordcount', showInMainToolbar: true, showInElementToolbar: true },
    { name: 'symbols', showInMainToolbar: true, showInElementToolbar: false },
    { name: 'buttoneditor', showInMainToolbar: false, showInElementToolbar: false },
];