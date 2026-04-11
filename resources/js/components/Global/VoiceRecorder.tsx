import { useState, useRef, useCallback } from 'react';
import { getTranscriptionToken } from '@/Api/ai';
import { MicrophoneIcon, StopIcon } from '@heroicons/react/solid';

interface VoiceRecorderProps {
    onTranscript: (text: string) => void;
    disabled?: boolean;
}

type RecordingState = 'idle' | 'connecting' | 'recording' | 'error';

export default function VoiceRecorder({ onTranscript, disabled = false }: VoiceRecorderProps) {
    const [state, setState] = useState<RecordingState>('idle');
    const [partialText, setPartialText] = useState('');
    const wsRef = useRef<WebSocket | null>(null);
    const mediaStreamRef = useRef<MediaStream | null>(null);
    const processorRef = useRef<ScriptProcessorNode | null>(null);
    const audioContextRef = useRef<AudioContext | null>(null);
    const committedTextRef = useRef('');

    const startRecording = useCallback(async () => {
        try {
            setState('connecting');
            committedTextRef.current = '';
            setPartialText('');

            const [tokenResponse, stream] = await Promise.all([
                getTranscriptionToken(),
                navigator.mediaDevices.getUserMedia({ audio: true }),
            ]);

            mediaStreamRef.current = stream;

            const wsUrl = new URL('wss://api.elevenlabs.io/v1/speech-to-text/realtime');
            wsUrl.searchParams.set('model_id', 'scribe_v2_realtime');
            wsUrl.searchParams.set('token', tokenResponse.token);
            wsUrl.searchParams.set('audio_format', 'pcm_16000');

            const ws = new WebSocket(wsUrl.toString());
            wsRef.current = ws;

            ws.onopen = () => {
                setState('recording');

                const audioContext = new AudioContext({ sampleRate: 16000 });
                audioContextRef.current = audioContext;
                const source = audioContext.createMediaStreamSource(stream);
                const processor = audioContext.createScriptProcessor(4096, 1, 1);
                processorRef.current = processor;

                processor.onaudioprocess = (e) => {
                    if (ws.readyState !== WebSocket.OPEN) return;

                    const inputData = e.inputBuffer.getChannelData(0);
                    const pcm16 = new Int16Array(inputData.length);
                    for (let i = 0; i < inputData.length; i++) {
                        const s = Math.max(-1, Math.min(1, inputData[i]));
                        pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
                    }

                    const uint8 = new Uint8Array(pcm16.buffer);
                    let binary = '';
                    for (let i = 0; i < uint8.length; i++) {
                        binary += String.fromCharCode(uint8[i]);
                    }
                    const base64 = btoa(binary);

                    ws.send(JSON.stringify({
                        message_type: 'input_audio_chunk',
                        audio_base_64: base64,
                    }));
                };

                source.connect(processor);
                processor.connect(audioContext.destination);
            };

            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);

                if (data.message_type === 'partial_transcript') {
                    const full = committedTextRef.current
                        ? committedTextRef.current + ' ' + data.text
                        : data.text;
                    setPartialText(full);
                    onTranscript(full);
                } else if (data.message_type === 'committed_transcript') {
                    committedTextRef.current = committedTextRef.current
                        ? committedTextRef.current + ' ' + data.text
                        : data.text;
                    setPartialText(committedTextRef.current);
                    onTranscript(committedTextRef.current);
                }
            };

            ws.onerror = () => {
                setState('error');
                cleanup();
            };

            ws.onclose = () => {
                if (state === 'recording') {
                    setState('idle');
                }
            };
        } catch (error) {
            console.error('Voice recording error:', error);
            setState('error');
            cleanup();
        }
    }, [onTranscript]);

    const cleanup = useCallback(() => {
        if (processorRef.current) {
            processorRef.current.disconnect();
            processorRef.current = null;
        }
        if (audioContextRef.current) {
            audioContextRef.current.close();
            audioContextRef.current = null;
        }
        if (mediaStreamRef.current) {
            mediaStreamRef.current.getTracks().forEach(track => track.stop());
            mediaStreamRef.current = null;
        }
        if (wsRef.current) {
            wsRef.current.close();
            wsRef.current = null;
        }
    }, []);

    const stopRecording = useCallback(() => {
        // Send commit signal before closing
        if (wsRef.current && wsRef.current.readyState === WebSocket.OPEN) {
            wsRef.current.send(JSON.stringify({
                message_type: 'input_audio_chunk',
                audio_base_64: '',
                commit: true,
            }));
        }

        // Small delay to allow final commit to process
        setTimeout(() => {
            cleanup();
            setState('idle');
        }, 300);
    }, [cleanup]);

    const handleClick = () => {
        if (disabled) return;

        if (state === 'recording') {
            stopRecording();
        } else if (state === 'idle' || state === 'error') {
            startRecording();
        }
    };

    return (
        <button
            type="button"
            onClick={handleClick}
            disabled={disabled || state === 'connecting'}
            className={`
        shrink-0 rounded-lg p-2 transition-colors
        ${state === 'recording'
                    ? 'bg-red-500 text-white hover:bg-red-600 animate-pulse'
                    : 'text-muted-foreground hover:text-foreground hover:bg-accent'
                }
        ${(disabled || state === 'connecting') ? 'opacity-50 cursor-not-allowed' : ''}
      `}
            title={state === 'recording' ? 'Stop recording' : 'Start voice input'}
        >
            {state === 'recording' ? (
                <StopIcon className="w-4 h-4" />
            ) : state === 'connecting' ? (
                <svg className="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" className="opacity-25" />
                    <path d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" fill="currentColor" className="opacity-75" />
                </svg>
            ) : (
                <MicrophoneIcon className="w-4 h-4" />
            )}
        </button>
    );
}
