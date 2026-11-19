/* Titik masuk JavaScript. */
import './modules/navigation.js';
import './modules/auth.js';
import './modules/checker.js';
import './modules/history.js';

const waktuLokal = document.querySelector('time[data-local-date-time]');
if (waktuLokal) {
    waktuLokal.textContent = new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(waktuLokal.dateTime));
}
