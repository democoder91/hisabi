import '@testing-library/jest-dom'

import { chat, submitToolResponse, transcribeAudio, uploadAiFile } from '../ai'

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

it('uploads ai chat files as multipart form data', async () => {
    document.cookie = 'XSRF-TOKEN=cookie-token; path=/'

    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        text: async () => '{"upload":{"id":7,"purpose":"ai-chat"}}',
    })

    const file = new File(['receipt'], 'receipt.pdf', { type: 'application/pdf' })
    const response = await uploadAiFile(file, 'ai-chat', { source: 'composer' })

    expect(response).toEqual({
        upload: {
            id: 7,
            purpose: 'ai-chat',
        },
    })

    const [url, options] = fetchSpy.mock.calls[0]

    expect(url).toBe('/api/v1/ai/uploads')
    expect(options.method).toBe('POST')
    expect(options.body).toBeInstanceOf(FormData)
    expect(options.body.get('file')).toBeInstanceOf(File)
    expect(options.body.get('purpose')).toBe('ai-chat')
    expect(options.body.get('custom_attributes[source]')).toBe('composer')
})

it('parses a successful chat response even when the body contains surrounding noise', async () => {
    document.cookie = 'XSRF-TOKEN=cookie-token; path=/'

    jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        text: async () => 'debug-prefix {"content":"Created successfully","conversation_id":"123"} debug-suffix',
    })

    const response = await chat([{ role: 'user', content: 'Create an account', upload_ids: [7] }])

    expect(response).toEqual({
        content: 'Created successfully',
        conversation_id: '123',
    })

    const [, options] = global.fetch.mock.calls[0]
    expect(JSON.parse(options.body)).toEqual({
        messages: [{ role: 'user', content: 'Create an account', upload_ids: [7] }],
        conversation_id: null,
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