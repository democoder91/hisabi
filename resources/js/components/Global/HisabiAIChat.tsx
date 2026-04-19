import { useEffect, useState } from 'react'
import { Link, usePage } from '@inertiajs/react';
import { chat } from '@/Api/ai';
import { XIcon } from '@heroicons/react/solid';
import { Message, MessageContent } from '@/components/ui/shadcn-io/ai/message';
import { Response } from '@/components/ui/shadcn-io/ai/response';
import { Conversation, ConversationContent } from '@/components/ui/shadcn-io/ai/conversation';
import { PromptInput, PromptInputTextarea, PromptInputToolbar, PromptInputSubmit } from '@/components/ui/shadcn-io/ai/prompt-input';
import { Suggestions, Suggestion } from '@/components/ui/shadcn-io/ai/suggestion';
import { Loader } from '@/components/ui/shadcn-io/ai/loader';
import AIChartRenderer from './AIChartRenderer';
import AIFinancialWidget from './AIFinancialWidget';
import VoiceRecorder from './VoiceRecorder';
import { Button } from '@/components/ui/button';

interface HisabiAIChatProps {
  onClose?: () => void;
  title?: string;
  subtitle?: string;
  emptyTitle?: string;
  emptyDescription?: string;
  placeholder?: string;
  defaultSuggestions?: string[];
  loadingText?: string;
}

interface ChatMessage {
  id: number;
  content: string;
  role: 'user' | 'assistant';
  charts?: any[];
  components?: any[];
  suggestions?: string[];
}

export default function HisabiAIChat({
  onClose,
  title = 'NexoAi',
  subtitle,
  emptyTitle = 'Start a conversation...',
  emptyDescription = 'Ask me anything about your finances!',
  placeholder = 'Ask about your finances...',
  defaultSuggestions,
  loadingText = 'Analyzing your finances...',
}: HisabiAIChatProps) {
  const { auth } = usePage<{ auth?: { user?: { available_credits?: number; is_super?: boolean } } }>().props;
  const isSuperUser = auth?.user?.is_super === true;
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [chatHistory, setChatHistory] = useState<ChatMessage[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [availableCredits, setAvailableCredits] = useState(auth?.user?.available_credits ?? 0);
  const [needsCredits, setNeedsCredits] = useState(!isSuperUser && (auth?.user?.available_credits ?? 0) < 1);

  const handleChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
    setMessage(event.target.value);
  };

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (message.trim() === '' || loading) return;

    const newMessage: ChatMessage = {
      id: chatHistory.length + 1,
      content: message,
      role: 'user',
    };

    setChatHistory([...chatHistory, newMessage]);
    setMessage('');
    setNeedsCredits(false);
  };

  useEffect(() => {
    if (chatHistory[chatHistory.length - 1]?.role === 'user') {
      submit(chatHistory);
    }
  }, [chatHistory]);


  const submit = async (newChat: ChatMessage[]) => {
    setLoading(true);

    try {
      const messages = newChat.map((msg) => ({
        role: msg.role,
        content: msg.content
      }));

      const aiResponse = await chat(messages, conversationId);

      if (!isSuperUser && typeof aiResponse.available_credits === 'number') {
        setAvailableCredits(aiResponse.available_credits);
      }

      if (aiResponse.conversation_id) {
        setConversationId(aiResponse.conversation_id);
      }

      const assistantMessage: ChatMessage = {
        id: newChat.length + 1,
        role: 'assistant',
        content: aiResponse.content,
        charts: aiResponse.charts || [],
        components: aiResponse.components || [],
        suggestions: aiResponse.suggestions || []
      };

      setChatHistory([...newChat, assistantMessage]);
    } catch (error) {
      if (!isSuperUser && error?.status === 402) {
        const remainingCredits = error?.payload?.available_credits ?? 0;

        setAvailableCredits(remainingCredits);
        setNeedsCredits(true);
        setChatHistory([
          ...newChat,
          {
            id: newChat.length + 1,
            role: 'assistant',
            content: error?.payload?.message || 'You are out of credits. Open billing to buy more and continue.',
            charts: [],
            components: [],
            suggestions: [],
          },
        ]);
      } else {
        console.error('AI Chat Error:', error);
        const errorMessage: ChatMessage = {
          id: newChat.length + 1,
          role: 'assistant',
          content: 'I apologize, but I encountered an error. Please try again.',
          charts: [],
          components: [],
          suggestions: []
        };
        setChatHistory([...newChat, errorMessage]);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSuggestionClick = (suggestionText: string) => {
    setMessage(suggestionText);
  };

  // Get suggestions from the last assistant message
  const lastAssistantMessage = [...chatHistory].reverse().find(msg => msg.role === 'assistant');
  const currentSuggestions = lastAssistantMessage?.suggestions || defaultSuggestions || [
    'Show me my spending summary for this month',
    'What are my top expenses?',
    'How much can I save this month?'
  ];

  return (
    <div className="h-full w-full flex flex-col overflow-hidden">
      {/* Header */}
      <div className="border-b p-4">
        <div className='flex justify-between items-center'>
          <div>
            <h2 className='text-lg font-semibold'>{title}</h2>
            <p className='text-xs text-muted-foreground'>
              {subtitle || (isSuperUser ? 'Unlimited AI access' : `${availableCredits} credits remaining`)}
            </p>
          </div>
          {onClose && (
            <button
              onClick={onClose}
              className="text-muted-foreground hover:text-foreground transition-colors"
            >
              <XIcon className='w-5 h-5' />
            </button>
          )}
        </div>
      </div>

      {/* Conversation Area */}
      <Conversation className="flex-1">
        <ConversationContent>
          {chatHistory.length === 0 && (
            <div className="flex items-center justify-center h-full">
              <div className="text-center space-y-2">
                <p className="text-muted-foreground text-sm">{emptyTitle}</p>
                <p className="text-xs text-muted-foreground">{emptyDescription}</p>
              </div>
            </div>
          )}

          {chatHistory.map((msg) => (
            <Message key={msg.id} from={msg.role}>
              <MessageContent>
                <Response>{msg.content}</Response>

                {/* Render charts if present */}
                {msg.charts && msg.charts.length > 0 && (
                  <div className="mt-4 space-y-4">
                    {msg.charts.map((chart, index) => (
                      <AIChartRenderer key={index} chart={chart} />
                    ))}
                  </div>
                )}

                {/* Render components if present */}
                {msg.components && msg.components.length > 0 && (
                  <div className="mt-4 space-y-4">
                    {msg.components.map((component, index) => (
                      <AIFinancialWidget key={index} widget={component} />
                    ))}
                  </div>
                )}
              </MessageContent>
            </Message>
          ))}

          {loading && (
            <div className="flex items-center gap-2 py-4">
              <Loader size={20} />
              <span className="text-sm text-muted-foreground">{loadingText}</span>
            </div>
          )}
        </ConversationContent>
      </Conversation>

      {/* Input Area */}
      <div className="p-4 space-y-3">
        {!isSuperUser && (needsCredits || availableCredits < 1) && (
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
            <p className="font-medium">You need more credits to continue chatting.</p>
            <p className="mt-1 text-xs opacity-80">Buy a top-up or start a subscription from the billing page.</p>
            <Button asChild variant="outline" className="mt-3 w-full">
              <Link href={route('billing.index')}>Open billing</Link>
            </Button>
          </div>
        )}

        <Suggestions>
          {currentSuggestions.slice(0, 3).map((suggestion, index) => (
            <Suggestion
              key={index}
              suggestion={suggestion}
              onClick={handleSuggestionClick}
            />
          ))}
        </Suggestions>

        <PromptInput onSubmit={handleSubmit}>
          <PromptInputTextarea
            value={message}
            onChange={handleChange}
            disabled={loading || (!isSuperUser && availableCredits < 1)}
            placeholder={placeholder}
          />
          <PromptInputToolbar>
            <VoiceRecorder
              onTranscript={(text) => setMessage(text)}
              disabled={loading || (!isSuperUser && availableCredits < 1)}
            />
            <PromptInputSubmit
              disabled={loading || message.trim() === '' || (!isSuperUser && availableCredits < 1)}
              status={loading ? 'streaming' : 'idle'}
            />
          </PromptInputToolbar>
        </PromptInput>
      </div>
    </div>
  )
}

