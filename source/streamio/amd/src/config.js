define([], function () {
    window.requirejs.config({
        paths: {
            "tus": M.cfg.wwwroot + '/admin/tool/mediatime/source/streamio/js/tus',
        },
        shim: {
            'tus': {exports: 'tus'},
        }
    });
});