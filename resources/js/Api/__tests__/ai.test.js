import '@testing-library/jest-dom'

import { chat, transcribeAudio } from '../ai'

afterEach(() => {
    jest.restoreAllMocks()
    document.head.innerHTML = ''
})

it('uploads recorded audio as multipart form data', async () => {
    document.head.innerHTML = '<meta name="csrf-token" content="csrf-token-value">'

    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        json: async () => ({ text: 'Coffee purchase for 18 dirhams' }),
    })

    const response = await transcribeAudio(new Blob(['audio'], { type: 'audio/webm' }), 'recording.webm')

    expect(response).toEqual({ text: 'Coffee purchase for 18 dirhams' })
    expect(fetchSpy).toHaveBeenCalledTimes(1)

    const [url, options] = fetchSpy.mock.calls[0]

    expect(url).toBe('/api/v1/ai/transcribe')
    expect(options.method).toBe('POST')
    expect(options.headers['X-CSRF-TOKEN']).toBe('csrf-token-value')
    expect(options.headers['X-Requested-With']).toBe('XMLHttpRequest')
    expect(options.body).toBeInstanceOf(FormData)
    expect(options.body.get('audio')).toBeInstanceOf(Blob)
})

it('parses a successful chat response even when the body contains surrounding noise', async () => {
    document.head.innerHTML = '<meta name="csrf-token" content="csrf-token-value">'

    jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        text: async () => 'debug-prefix {"content":"Created successfully","conversation_id":"123"} debug-suffix',
    })

    const response = await chat([{ role: 'user', content: 'Create an account' }])

    expect(response).toEqual({
        content: 'Created successfully',
        conversation_id: '123',
    })
})