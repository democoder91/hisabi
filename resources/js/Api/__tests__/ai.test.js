import '@testing-library/jest-dom'

import { chat, submitToolResponse, transcribeAudio } from '../ai'

afterEach(() => {
    jest.restoreAllMocks()
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
    document.head.innerHTML = ''
})

it('uploads recorded audio as multipart form data', async () => {
    document.cookie = 'XSRF-TOKEN=cookie-token; path=/'

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
    expect(options.headers.get('X-XSRF-TOKEN')).toBe('cookie-token')
    expect(options.headers.get('X-Requested-With')).toBe('XMLHttpRequest')
    expect(options.body).toBeInstanceOf(FormData)
    expect(options.body.get('audio')).toBeInstanceOf(Blob)
})

it('parses a successful chat response even when the body contains surrounding noise', async () => {
    document.cookie = 'XSRF-TOKEN=cookie-token; path=/'

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

it('submits structured tool responses to the zero-credit continuation endpoint', async () => {
    document.cookie = 'XSRF-TOKEN=cookie-token; path=/'

    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        text: async () => '{"status":"completed","content":"Done","conversation_id":"conversation-1"}',
    })

    const response = await submitToolResponse('conversation-1', {
        account_id: 'checking',
        tags: ['food', 'team'],
    })

    expect(response).toEqual({
        status: 'completed',
        content: 'Done',
        conversation_id: 'conversation-1',
    })

    const [url, options] = fetchSpy.mock.calls[0]

    expect(url).toBe('/api/v1/ai/chat/conversation-1/tool-response')
    expect(options.method).toBe('POST')
    expect(JSON.parse(options.body)).toEqual({
        answers: {
            account_id: 'checking',
            tags: ['food', 'team'],
        },
    })
})