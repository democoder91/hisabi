export default function ApplicationLogo() {
    return (
        <div data-testid="application-logo" className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-xl border border-dashed border-primary/40 bg-primary/5 text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">
                NX
            </div>
            <div className="min-w-0">
                <p className="text-sm font-semibold uppercase tracking-[0.24em] text-foreground">Nexo</p>
                <p className="text-xs text-muted-foreground">Logo placeholder</p>
            </div>
        </div>
    );
}
