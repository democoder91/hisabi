import { useState, useRef, useCallback, useEffect } from 'react';
import { transcribeAudio } from '@/Api/ai';
import { MicrophoneIcon, StopIcon } from '@heroicons/react/solid';

interface VoiceRecorderProps {
    onTranscript: (text: string) => void;
    disabled?: boolean;
}

type RecordingState = 'idle' | 'connecting' | 'recording' | 'transcribing' | 'error';

const recordingMimeTypes = [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/mp4',
    'audio/ogg;codecs=opus',
    'audio/ogg',
];

const getSupportedMimeType = (): string => {
    if (typeof MediaRecorder === 'undefined') {
        return '';
    }

    if (typeof MediaRecorder.isTypeSupported !== 'function') {
        return recordingMimeTypes[0];
    }

    return recordingMimeTypes.find((mimeType) => MediaRecorder.isTypeSupported(mimeType)) ?? '';
};

const getRecordingFilename = (mimeType: string): string => {
    if (mimeType.includes('mp4')) {
        return 'recording.m4a';
    }

    if (mimeType.includes('ogg')) {
        return 'recording.ogg';
    }

    return 'recording.webm';
};

export default function VoiceRecorder({ onTranscript, disabled = false }: VoiceRecorderProps) {
    const [state, setState] = useState<RecordingState>('idle');
    const mediaRecorderRef = useRef<MediaRecorder | null>(null);
    const mediaStreamRef = useRef<MediaStream | null>(null);
    const audioChunksRef = useRef<Blob[]>([]);
    const shouldUploadRef = useRef(false);

    const stopMediaStream = useCallback(() => {
        if (mediaStreamRef.current) {
            mediaStreamRef.current.getTracks().forEach((track) => track.stop());
            mediaStreamRef.current = null;
        }
    }, []);

    const resetRecorder = useCallback(() => {
        if (mediaRecorderRef.current) {
            mediaRecorderRef.current.ondataavailable = null;
            mediaRecorderRef.current.onerror = null;
            mediaRecorderRef.current.onstop = null;
            mediaRecorderRef.current = null;
        }

        audioChunksRef.current = [];
    }, []);

    const startRecording = useCallback(async () => {
        try {
            if (typeof MediaRecorder === 'undefined') {
                throw new Error('MediaRecorder is not supported in this browser.');
            }

            setState('connecting');
            shouldUploadRef.current = true;
            audioChunksRef.current = [];

            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            mediaStreamRef.current = stream;

            const mimeType = getSupportedMimeType();
            const recorder = mimeType
                ? new MediaRecorder(stream, { mimeType })
                : new MediaRecorder(stream);

            mediaRecorderRef.current = recorder;

            recorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunksRef.current.push(event.data);
                }
            };

            recorder.onerror = () => {
                shouldUploadRef.current = false;
                resetRecorder();
                stopMediaStream();
                setState('error');
            };

            recorder.onstop = async () => {
                const recordedMimeType = recorder.mimeType || mimeType || 'audio/webm';
                const audioBlob = new Blob(audioChunksRef.current, { type: recordedMimeType });

                resetRecorder();
                stopMediaStream();

                if (!shouldUploadRef.current || audioBlob.size === 0) {
                    shouldUploadRef.current = false;
                    setState('idle');

                    return;
                }

                try {
                    const transcript = await transcribeAudio(audioBlob, getRecordingFilename(recordedMimeType));
                    onTranscript(transcript.text);
                    setState('idle');
                } catch (error) {
                    console.error('Voice transcription error:', error);
                    setState('error');
                } finally {
                    shouldUploadRef.current = false;
                }
            };

            recorder.start();
            setState('recording');
        } catch (error) {
            console.error('Voice recording error:', error);
            shouldUploadRef.current = false;
            resetRecorder();
            stopMediaStream();
            setState('error');
        }
    }, [onTranscript, resetRecorder, stopMediaStream]);

    useEffect(() => {
        return () => {
            shouldUploadRef.current = false;

            if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
                mediaRecorderRef.current.stop();
            }

            resetRecorder();
            stopMediaStream();
        };
    }, [resetRecorder, stopMediaStream]);

    const stopRecording = useCallback(() => {
        const recorder = mediaRecorderRef.current;

        if (!recorder) {
            resetRecorder();
            stopMediaStream();
            setState('idle');

            return;
        }

        if (recorder.state === 'inactive') {
            setState('transcribing');

            return;
        }

        setState('transcribing');
        recorder.stop();
    }, [resetRecorder, stopMediaStream]);

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
            disabled={disabled || state === 'connecting' || state === 'transcribing'}
            className={`
        shrink-0 rounded-lg p-2 transition-colors
        ${state === 'recording'
                    ? 'bg-red-500 text-white hover:bg-red-600 animate-pulse'
                    : 'text-muted-foreground hover:text-foreground hover:bg-accent'
                }
        ${(disabled || state === 'connecting' || state === 'transcribing') ? 'opacity-50 cursor-not-allowed' : ''}
      `}
            title={state === 'recording' ? 'Stop recording' : 'Start voice input'}
        >
            {state === 'recording' ? (
                <StopIcon className="w-4 h-4" />
            ) : state === 'connecting' || state === 'transcribing' ? (
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
