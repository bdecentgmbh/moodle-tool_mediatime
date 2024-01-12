var upload;

import * as tus from 'mediatimesrc_streamio/tus';
import Log from 'core/log';

const options = {
    endpoint: 'https://dev.deftly.us/api/v1/videos/tus',
    metadata: {},
    onError: Log.debug,
    onProgress: function(bytesUploaded, bytesTotal) {
        document.querySelectorAll('.progress').forEach(indicator => {
            indicator.style.width = (bytesUploaded / bytesTotal * 100) + '%';
        });
    },
    onSuccess: function() {
        document.getElementById('upload_resource_form').submit();
        Log.debug(upload.url);
    }
};

export default {
    init: function(token) {
        options.metadata.uploadtoken = token;
        document.body.removeEventListener('click', this.handleClick);
        document.body.addEventListener('click', this.handleClick);
    },

    handleClick: function(e) {
        const button = e.target.closest('button[name="upload"]');
        if (button) {
            const file = document.querySelector('input[name="streamiofile"]').files[0];
            e.preventDefault();
            options.metadata.filename = file.name;
            options.metadata.filetype = file.type;
            Log.debug(options);
            Log.debug(tus);

            upload = new tus.Upload(file, options);
            upload.start();
        }
    }
};
