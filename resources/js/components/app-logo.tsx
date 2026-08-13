export default function AppLogo() {
    return (
        <>
            <img
                src="/images/magnetic-logo.jpg"
                alt=""
                aria-hidden="true"
                className="site-logo-blend size-9 shrink-0 object-cover object-top"
            />
            <div className="ml-1 grid flex-1 text-left leading-none">
                <span
                    className="text-sidebar-foreground truncate text-sm font-bold tracking-wide"
                    style={{ fontFamily: 'var(--font-display)' }}
                >
                    MAGNETIC
                </span>
                <span className="text-sidebar-primary text-[0.5rem] font-semibold tracking-[0.3em]">
                    BARBERSHOP
                </span>
            </div>
        </>
    );
}
