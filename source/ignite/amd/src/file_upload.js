import Ajax from 'core/ajax';
import Config from 'core/config';
import Log from 'core/log';
import Notification from 'core/notification';

const CHUNK_SIZE = 10 * 2 ** 20;

/**
 * Handle uploading
 *
 * @param {object} data Data for upload
 */
const upload = async(data) => {
    const file = document.querySelector('input[name="videofile"]').files[0];
    const parts = [];
    let offset = 0;

    for (let i = 0; i < data.parts.length; i++) {
        parts.push({
            PartNumber: data.parts[i].partNumber,
            ETag: await uploadPart(file, data.parts[i], offset, data)
        });
        offset++;
        document.querySelector('.progress').style.width = Math.min(CHUNK_SIZE * offset / file.size * 100, 100) + '%';
    }

    Ajax.call([{
        args: {
            id: data.id,
            key: data.key,
            parts: parts,
            uploadid: data.uploadid
        },
        contextid: document.querySelector('input[name="contextid"]').value,
        done: () => {
            const url = new URL(Config.wwwroot + '/admin/tool/mediatime');
            url.searchParams.set('id', data.id);
            window.location.href = url;
        },
        fail: Notification.exception,
        methodname: 'mediatimesrc_ignite_finish_upload'
    }]);
};

/**
 * Upload part
 *
 * @param {File} file File object
 * @param {Object} part Part to upload
 * @param {int} offset Index for part
 * @param {Object} data Upload url information
 * @returns {Object}
 */
const uploadPart = async(file, part, offset, data) => {
    const request = new Request(part.url, {
        body: file.slice(offset * CHUNK_SIZE, Math.min((offset * CHUNK_SIZE, file.size))),
        method: 'PUT'
    });
    try {
        const response = await fetch(request);
        if (response && response.ok) {
            return await JSON.parse(response.headers.get('Etag'));
        }
    } catch (e) {
        Log.debug(e);
    }
    return await reattempt(file, part, offset, data);
};

/**
 * Reattempt failed partial upload
 *
 * @param {File} file File object
 * @param {Object} part Part to upload
 * @param {int} offset Index for part
 * @param {Object} data Upload url information
 * @returns {Object}
 */
const reattempt = async(file, part, offset, data) => {
    await new Promise(resolve => setTimeout(resolve, 1000));
    try {
        const result = await Ajax.call([{
                args: {
                contextid: document.querySelector('input[name="contextid"]').value,
                key: data.key,
                partnumber: part.partNumber,
                uploadid: data.uploadid
            },
            contextid: document.querySelector('input[name="contextid"]').value,
            methodname: 'mediatimesrc_ignite_reattempt_upload'
        }])[0];
        part.url = result.url;
        return await uploadPart(file, part, offset, data);
    } catch (e) {
        Log.debug(e);
        return await reattempt(file, part, offset, data);
    }
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
    handleClick: async function(e) {
        const button = e.target.closest('button[name="upload"]');
        if (button) {
            const file = document.querySelector('input[name="videofile"]').files[0];
            if (!file) {
                Notification.alert('nofile', 'File missing');
                return;
            }
            button.disabled = true;
            e.preventDefault();
            Log.debug(file);
            const data = await Ajax.call([{
                args: {
                    filesize: Number(file.size),
                    contextid: document.querySelector('input[name="contextid"]').value,
                    description: document.querySelector('input[name="description"]').value,
                    groupid: document.querySelector('input[name="groupid"]').value || 0,
                    mimetype: file.type,
                    name: document.querySelector('input[name="name"]').value,
                    parts: Math.ceil(file.size / CHUNK_SIZE),
                    subtitlelanguage: document.querySelector('input[name="subtitlelanguage"]').value,
                    tags: document.querySelector('input[name="tags"]').value,
                    title: document.querySelector('input[name="title"]').value
                },
                contextid: document.querySelector('input[name="contextid"]').value,
                fail: Notification.exception,
                methodname: 'mediatimesrc_ignite_create_token'
            }])[0];
            upload(data);
        }
    }
};
