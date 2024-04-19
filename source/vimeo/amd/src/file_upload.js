import Ajax from 'core/ajax';
import Config from 'core/config';
import Log from 'core/log';
import Notification from 'core/notification';

const upload = async(resource) => {
    const file = document.querySelector('input[name="videofile"]').files[0];
    const url = new URL(Config.wwwroot + '/admin/tool/mediatime/index.php');
    let offset = 0;
    let response;

    url.searchParams.set('id', resource.id);

    do {
        const request = new Request(resource.uploadurl, {
            body: file,
            headers: {
                'Tus-Resumable': '1.0.0',
                'Upload-Offset': String(offset),
                'Content-Type': 'application/offset+octet-stream'
            },
            method: 'PATCH'
        });
        response = await fetch(request);
        document.querySelector('.progress').style.width = (response.headers.get('Upload-Offset') / file.size * 100) + '%';
        Log.debug(response.headers.get('Upload-Offset'));
    } while (response.headers.get('Upload-Offset') < file.size);
    window.location.href = url;
};

export default {
    init: function() {
        document.body.removeEventListener('click', this.handleClick);
        document.body.addEventListener('click', this.handleClick);
    },

    handleClick: function(e) {
        const button = e.target.closest('button[name="upload"]');
        if (button) {
            const file = document.querySelector('input[name="videofile"]').files[0];
            e.preventDefault();
            Ajax.call([{
                args: {
                    filesize: Number(file.size),
                    description: document.querySelector('input[name="description"]').value,
                    name: document.querySelector('input[name="name"]').value,
                    tags: document.querySelector('input[name="tags"]').value,
                    title: document.querySelector('input[name="title"]').value
                },
                contextid: 1,
                done: upload,
                fail: Notification.exception,
                methodname: 'mediatimesrc_vimeo_create_token'
            }]);
            Log.debug(file.size);
        }
    }
};
