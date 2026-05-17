import axios from 'axios'
import { ref } from 'vue'

export function useApi() {
    const loading = ref(false)
    const error   = ref(null)

    async function request(method, url, data = null, config = {}) {
        loading.value = true
        error.value   = null
        try {
            const response = await axios({ method, url: '/api/' + url, data, ...config })
            return response.data
        } catch (err) {
            error.value = err.response?.data?.message || err.message
            throw err
        } finally {
            loading.value = false
        }
    }

    return {
        loading,
        error,
        get:    (url, params)   => request('get', url, null, { params }),
        post:   (url, data)     => request('post', url, data),
        put:    (url, data)     => request('put', url, data),
        patch:  (url, data)     => request('patch', url, data),
        delete: (url)           => request('delete', url),
    }
}
