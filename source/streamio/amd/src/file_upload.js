/*
 * Upload video file to Streamio
 *
 * @package    tool_mediatime
 * @subpackage mediatimesrc_streamio
 * @module     mediatimesrc_streamio/file_upload
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log', 'mediatimesrc_streamio/tus'], function(Log, tus) {

    /**
     * Initialises FileUpload
     *
     * @class
     * @param {string} token
     */
    var FileUpload = function(token) {
        this.upload = {};
        this.options = {
            endpoint: 'https://streamio.com/api/v1/videos/tus',
            metadata: {uploadtoken: token},
            onError: Log.debug,
            onProgress: function(bytesUploaded, bytesTotal) {
                document.querySelectorAll('.progress').forEach((indicator) => {
                    indicator.style.width = (bytesUploaded / bytesTotal * 100) + '%';
                });
            },
            onSuccess: function() {
                document.getElementById('upload_resource_form').submit();
                Log.debug(this.upload.url);
            }
        };

        this.addEvents();
    };

    FileUpload.prototype = {
        /** @type {Object} */
        options: {},
        /** @type {Object} */
        upload: {},

        /**
         * Adds events to the current FileUpload
         */
        addEvents: function() {
            document.body.removeEventListener('click', this.handleClick.bind(this));
            document.body.addEventListener('click', this.handleClick.bind(this));
        },

        handleClick: function(e) {
            const button = e.target.closest('button[name="upload"]');
            if (button) {
                const file = document.querySelector('input[name="streamiofile"]').files[0];
                e.preventDefault();

                if (file) {
                    this.options.metadata.filename = file.name;
                    this.options.metadata.filetype = file.type;
                    Log.debug(this.options);
                    Log.debug(tus);

                    this.upload = new tus.Upload(file, this.options);
                    this.upload.start();
                }
            }
        }
    };

    return {
        /**
         * Initialises the component
         *
         * @param {string} token
         * @returns {Promise} resolved once complete
         */
        init: function(token) {
            var fileupload = new FileUpload(token);

            return Promise.resolve(fileupload);
        }
    };
});
