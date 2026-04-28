import React from 'react';

type Props = {
    children: React.ReactNode;
};

type State = {
    hasError: boolean;
};

export default class AppErrorBoundary extends React.Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(): State {
        return { hasError: true };
    }

    componentDidCatch(error: Error, errorInfo: React.ErrorInfo): void {
        // Keep this visible in dev tools for quick diagnostics.
        console.error('Unhandled UI error:', error, errorInfo);
    }

    render(): React.ReactNode {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
                    <div className="max-w-lg w-full rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                        <h1 className="text-xl font-semibold text-slate-900">Something went wrong</h1>
                        <p className="mt-3 text-sm text-slate-600">
                            We hit an unexpected error while rendering this page. Please refresh and try again.
                        </p>
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="mt-6 inline-flex items-center rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700"
                        >
                            Reload page
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}