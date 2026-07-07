import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';

export default function ApiDocs({ auth }: PageProps) {
    const { user } = auth;
    // The full key is only revealed once at generation (Settings page); docs
    // show the masked form so the plaintext never appears in page payloads.
    const maskedKey = user.api_key_last4
        ? `zvk_live_••••••••••••${user.api_key_last4}`
        : null;

    const apiUrl = `${window.location.origin}/api/v1`;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-bold text-slate-900">API Documentation</h2>}>
            <Head title="API Documentation" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    
                    {/* API Key Section */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8 border border-emerald-100">
                        <div className="p-6 bg-emerald-50/50 border-b border-emerald-100">
                            <h3 className="text-lg font-bold text-emerald-900">Your API Key</h3>
                            <p className="mt-1 text-sm text-emerald-700">
                                Authenticate requests with your API key. The full key is shown only once when
                                generated in <a href={route('settings.index')} className="underline font-semibold">Settings</a> — keep it secret.
                            </p>
                            
                            <div className="mt-4 flex items-center max-w-lg">
                                <input 
                                    type="text" 
                                    readOnly 
                                    value={maskedKey ?? 'No API key generated yet — create one in Settings.'}
                                    className="block w-full rounded-md border-emerald-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm bg-white font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Docs Section */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-xl font-bold text-slate-900 mb-6">Standard SMM API v2</h3>
                            
                            <p className="text-sm text-slate-600 mb-6">
                                Our API uses the standard SMM Panel structure, making it 100% compatible with existing panels and scripts.
                                All requests should be made via <code>POST</code> to <code className="bg-slate-100 px-2 py-1 rounded text-pink-600">{apiUrl}</code>.
                            </p>

                            <div className="space-y-8">
                                {/* Services */}
                                <section>
                                    <h4 className="text-lg font-semibold text-slate-800 border-b pb-2 mb-4">1. Service List</h4>
                                    <p className="text-sm text-slate-600 mb-2">Returns a list of all available services.</p>
                                    <div className="bg-slate-900 rounded-md p-4 text-emerald-400 font-mono text-sm overflow-x-auto">
                                        curl -X POST {apiUrl} \<br/>
                                        &nbsp;&nbsp;-d "key=YOUR_API_KEY" \<br/>
                                        &nbsp;&nbsp;-d "action=services"
                                    </div>
                                </section>

                                {/* Add Order */}
                                <section>
                                    <h4 className="text-lg font-semibold text-slate-800 border-b pb-2 mb-4">2. Add Order</h4>
                                    <p className="text-sm text-slate-600 mb-2">Places a new order.</p>
                                    <div className="bg-slate-900 rounded-md p-4 text-emerald-400 font-mono text-sm overflow-x-auto">
                                        curl -X POST {apiUrl} \<br/>
                                        &nbsp;&nbsp;-d "key=YOUR_API_KEY" \<br/>
                                        &nbsp;&nbsp;-d "action=add" \<br/>
                                        &nbsp;&nbsp;-d "service=1" \<br/>
                                        &nbsp;&nbsp;-d "link=https://instagram.com/example" \<br/>
                                        &nbsp;&nbsp;-d "quantity=1000"
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500"><strong>Response:</strong> <code>{"{\"order\": 12345}"}</code></p>
                                </section>

                                {/* Order Status */}
                                <section>
                                    <h4 className="text-lg font-semibold text-slate-800 border-b pb-2 mb-4">3. Order Status</h4>
                                    <p className="text-sm text-slate-600 mb-2">Check the status of a single order or multiple orders (comma-separated).</p>
                                    <div className="bg-slate-900 rounded-md p-4 text-emerald-400 font-mono text-sm overflow-x-auto">
                                        curl -X POST {apiUrl} \<br/>
                                        &nbsp;&nbsp;-d "key=YOUR_API_KEY" \<br/>
                                        &nbsp;&nbsp;-d "action=status" \<br/>
                                        &nbsp;&nbsp;-d "orders=12345,12346"
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500"><strong>Response:</strong> <code>{"{\"12345\": {\"status\": \"Completed\", \"charge\": \"1.20\", \"remains\": 0}}"}</code></p>
                                </section>

                                {/* Balance */}
                                <section>
                                    <h4 className="text-lg font-semibold text-slate-800 border-b pb-2 mb-4">4. Account Balance</h4>
                                    <p className="text-sm text-slate-600 mb-2">Check your current account balance.</p>
                                    <div className="bg-slate-900 rounded-md p-4 text-emerald-400 font-mono text-sm overflow-x-auto">
                                        curl -X POST {apiUrl} \<br/>
                                        &nbsp;&nbsp;-d "key=YOUR_API_KEY" \<br/>
                                        &nbsp;&nbsp;-d "action=balance"
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500"><strong>Response:</strong> <code>{"{\"balance\": \"150.00\", \"currency\": \"USD\"}"}</code></p>
                                </section>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
