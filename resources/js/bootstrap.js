import axios from 'axios';
import "bootstrap-icons/font/bootstrap-icons.css";
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
