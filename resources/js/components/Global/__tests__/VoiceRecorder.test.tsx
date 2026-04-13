import * as React from 'react'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'

import VoiceRecorder from '../VoiceRecorder'
import { transcribeAudio } from '@/Api/ai'

jest.mock('@/Api/ai', () => ({
    transcribeAudio: jest.fn(),
}))

class MockMediaRecorder {
    static isTypeSupported(mimeType: string) {
        return mimeType === 'audio/webm;codecs=opus' || mimeType === 'audio/webm'
    }

    mimeType: string
    state: 'inactive' | 'recording' = 'inactive'
    ondataavailable: ((event: { data: Blob }) => void) | null = null
    onerror: (() => void) | null = null
    onstop: (() => void) | null = null

    constructor(_stream: MediaStream, options?: MediaRecorderOptions) {
        this.mimeType = options?.mimeType ?? 'audio/webm'
    }

    start() {
        this.state = 'recording'
    }

    stop() {
        this.state = 'inactive'

        if (this.ondataavailable) {
            this.ondataavailable({ data: new Blob(['audio'], { type: this.mimeType }) })
        }

        if (this.onstop) {
            this.onstop()
        }
    }
}

afterEach(() => {
    cleanup()
    jest.clearAllMocks()
})

it('records audio locally and uploads it once after stopping', async () => {
    const tracks = [{ stop: jest.fn() }]
    const getUserMedia = jest.fn().mockResolvedValue({
        getTracks: () => tracks,
    })

    Object.defineProperty(window, 'MediaRecorder', {
        writable: true,
        value: MockMediaRecorder,
    })

    Object.defineProperty(global, 'MediaRecorder', {
        writable: true,
        value: MockMediaRecorder,
    })

    Object.defineProperty(navigator, 'mediaDevices', {
        writable: true,
        value: { getUserMedia },
    })

        ; (transcribeAudio as jest.Mock).mockResolvedValue({ text: 'Coffee purchase for 18 dirhams' })

    const onTranscript = jest.fn()
    const user = userEvent.setup()

    render(<VoiceRecorder onTranscript={onTranscript} />)

    await user.click(screen.getByRole('button'))
    await user.click(screen.getByRole('button'))

    await waitFor(() => {
        expect(transcribeAudio).toHaveBeenCalledTimes(1)
    })

    expect(getUserMedia).toHaveBeenCalledWith({ audio: true })
    expect(transcribeAudio).toHaveBeenCalledWith(expect.any(Blob), 'recording.webm')
    expect(onTranscript).toHaveBeenCalledWith('Coffee purchase for 18 dirhams')
    expect(tracks[0].stop).toHaveBeenCalledTimes(1)
})