import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SparkleIcon, ChatCircleTextIcon, X } from '@phosphor-icons/react';
import {
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
} from '@/components/ui/sidebar';
import HisabiAIChat from './Global/HisabiAIChat';
import SmsParser from './Global/SmsParser';

export default function RightSidebar() {
  const { t } = useTranslation();
  const [activePanel, setActivePanel] = useState<'ai' | 'sms' | null>(null);
  const [mobileFabOpen, setMobileFabOpen] = useState(false);

  const togglePanel = (panel: 'ai' | 'sms') => {
    if (activePanel === panel) {
      setActivePanel(null);
      setMobileFabOpen(false);
    } else {
      setActivePanel(panel);
    }
  };

  const closePanel = () => {
    setActivePanel(null);
    setMobileFabOpen(false);
  };

  return (
    <>
      {/* Desktop: existing right sidebar */}
      <div className="hidden md:flex h-screen bg-sidebar">
        {/* Narrow sidebar with vertical labels */}
        <div className="w-12 flex-shrink-0 bg-sidebar flex flex-col items-center pr-2 py-2 gap-1">
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton
                onClick={() => togglePanel('ai')}
                isActive={activePanel === 'ai'}
                size="sm"
                className="flex flex-col items-center gap-1 h-auto py-3"
              >
                <SparkleIcon size={18} />
                <span 
                  className="font-medium whitespace-nowrap"
                  style={{ writingMode: 'vertical-lr', transform: 'rotate(0deg)' }}
                >
                  {t('ai.titleBeta')}
                </span>
              </SidebarMenuButton>
            </SidebarMenuItem>
                      
            <SidebarMenuItem>
              <SidebarMenuButton
                onClick={() => togglePanel('sms')}
                isActive={activePanel === 'sms'}
                size="sm"
                className="flex flex-col items-center gap-1 h-auto py-3"
              >
                <ChatCircleTextIcon size={18} />
                <span
                  className="font-medium whitespace-nowrap"
                  style={{ writingMode: 'vertical-lr', transform: 'rotate(0deg)' }}
                >
                  {t('smsParser.title')}
                </span>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </div>

        {/* Expandable content panel */}
        <div className={`overflow-hidden transition-all duration-300 ease-in-out border-l ${
          activePanel ? 'w-[400px]' : 'w-0'
        }`}>
          {activePanel === 'ai' && <HisabiAIChat onClose={closePanel} />}
          {activePanel === 'sms' && <SmsParser onClose={closePanel} />}
        </div>
      </div>

      {/* Mobile: floating action button + full-screen overlay */}
      <div className="md:hidden">
        {/* FAB */}
        {!activePanel && (
          <button
            onClick={() => setMobileFabOpen(!mobileFabOpen)}
            className="fixed bottom-6 right-6 z-50 size-14 rounded-full bg-primary text-primary-foreground shadow-lg flex items-center justify-center hover:bg-primary/90 active:scale-95 transition-transform"
          >
            {mobileFabOpen ? <X size={24} /> : <SparkleIcon size={24} />}
          </button>
        )}

        {/* FAB menu options */}
        {mobileFabOpen && !activePanel && (
          <div className="fixed bottom-24 right-6 z-50 flex flex-col gap-3 items-end">
            <button
              onClick={() => { setMobileFabOpen(false); togglePanel('ai'); }}
              className="flex items-center gap-2 rounded-full bg-background border shadow-lg px-4 py-2.5 text-sm font-medium hover:bg-accent transition-colors"
            >
              <SparkleIcon size={18} />
              {t('ai.title')}
            </button>
            <button
              onClick={() => { setMobileFabOpen(false); togglePanel('sms'); }}
              className="flex items-center gap-2 rounded-full bg-background border shadow-lg px-4 py-2.5 text-sm font-medium hover:bg-accent transition-colors"
            >
              <ChatCircleTextIcon size={18} />
              {t('smsParser.title')}
            </button>
          </div>
        )}

        {/* Backdrop to close FAB menu */}
        {mobileFabOpen && !activePanel && (
          <div
            className="fixed inset-0 z-40"
            onClick={() => setMobileFabOpen(false)}
          />
        )}

        {/* Full-screen panel overlay */}
        {activePanel && (
          <div className="fixed inset-0 z-50 bg-background flex flex-col">
            {activePanel === 'ai' && <HisabiAIChat onClose={closePanel} />}
            {activePanel === 'sms' && <SmsParser onClose={closePanel} />}
          </div>
        )}
      </div>
    </>
  );
}

