import Ajax from 'core/ajax';
import Config from 'core/config';
import Log from 'core/log';
import Notification from 'core/notification';

/**
 * Handle uploading
 *
 * @param {object} resource Resource record
 */
const upload = async (resource) => {
    const file = document.querySelector('input[name="videofile"]').files[0];
    const url = new URL(Config.wwwroot + '/admin/tool/mediatime/index.php');
    let offset = 0;

    url.searchParams.set('id', resource.id);

    do {
        const request = new Request(resource.uploadurl, {
            body: file.slice(Number(offset), Number(offset) + 128*2**20),
            headers: {
                'Tus-Resumable': '1.0.0',
                'Upload-Offset': String(offset),
                'Content-Type': 'application/offset+octet-stream'
            },
            method: 'PATCH'
        });
        const response = await fetch(request).catch(e => {
            Log.debug(e);
            return new Promise(resolve => setTimeout(resolve, 1000));
        });
        if (response && response.ok) {
            offset = response.headers.get('Upload-Offset');
        }
        document.querySelector('.progress').style.width = ( offset / file.size * 100) + '%';
    } while (offset < file.size);
    window.location.href = url;
};

export default {
    /**
     * Init event listeners
     */
    init: function() {
        document.body.removeEventListener('click', this.handleClick);
        document.body.addEventListener('click', this.handleClick);
    },

    /**
     * Handle button click
     *
     * @param {Event} e Click event
     */
    handleClick: function(e) {
        const button = e.target.closest('button[name="upload"]');
        if (button) {
            const file = document.querySelector('input[name="videofile"]').files[0];
            e.preventDefault();
            Ajax.call([{
                args: {
                    filesize: Number(file.size),
                    contextid: document.querySelector('input[name="contextid"]').value,
                    description: document.querySelector('input[name="description"]').value,
                    groupid: document.querySelector('input[name="groupid"]').value || 0,
                    name: document.querySelector('input[name="name"]').value,
                    parenturi: document.querySelector('input[name="parenturi"]').value,
                    tags: document.querySelector('input[name="tags"]').value,
                    title: document.querySelector('input[name="title"]').value
                },
                contextid: document.querySelector('input[name="contextid"]').value,
                done: upload,
                fail: Notification.exception,
                methodname: 'mediatimesrc_vimeo_create_token'
            }]);
        }
    }
};
