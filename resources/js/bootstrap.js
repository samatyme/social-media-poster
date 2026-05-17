import axios from 'axios'
window.axios = axios

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// Restore auth token from localStorage on every page load
const token = localStorage.getItem('auth_token')
if (token) {
    window.axios.defaults.headers.common['Authorization'] = 'Bearer ' + token
}

// Redirect to login if a request returns 401
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token')
            delete window.axios.defaults.headers.common['Authorization']
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)
