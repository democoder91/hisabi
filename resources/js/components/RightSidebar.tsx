import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { SparkleIcon } from '@phosphor-icons/react';
import HisabiAIChat from './Global/HisabiAIChat';

export default function RightSidebar() {
  const { direction = 'ltr' } = usePage<{ direction?: string }>().props as {
    direction?: string;
  };
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);

  const closePanel = () => setIsOpen(false);
  const buttonPositionClass = direction === 'rtl' ? 'left-4 sm:left-6' : 'right-4 sm:right-6';
  const panelPositionClass = direction === 'rtl'
    ? 'left-4 sm:left-6 origin-bottom-left'
    : 'right-4 sm:right-6 origin-bottom-right';

  return (
    <>
      {isOpen && (
        <button
          type="button"
          aria-label="Close NexoAi panel overlay"
          className="fixed inset-0 z-40 bg-black/20 backdrop-blur-[1px]"
          onClick={closePanel}
        />
      )}

      {!isOpen && (
        <button
          type="button"
          aria-label={t('ai.title')}
          data-testid="ai-floating-button"
          onClick={() => setIsOpen(true)}
          className={`fixed bottom-4 ${buttonPositionClass} z-50 inline-flex items-center gap-2 rounded-full bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground shadow-lg transition-transform hover:bg-primary/90 active:scale-95`}
        >
          <SparkleIcon size={18} weight="fill" />
          <span>{t('ai.title')}</span>
        </button>
      )}

      {isOpen && (
        <div
          data-testid="ai-floating-panel"
          className={`fixed bottom-4 ${panelPositionClass} z-50 h-[min(42rem,calc(100vh-5rem))] w-[calc(100vw-2rem)] max-w-[26rem] overflow-hidden rounded-3xl border bg-background shadow-2xl`}
        >
          <HisabiAIChat onClose={closePanel} />
        </div>
      )}
    </>
  );
}

