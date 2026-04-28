import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Copy, Check } from 'lucide-react';

export default function ApiDocs({ auth }: PageProps) {
    const { user } = auth;
    const [copied, setCopied] = useState(false);

    const copyKey = () => {
        navigator.clipboard.writeText(user.api_key || '');
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

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
                            <p className="mt-1 text-sm text-emerald-700">Use this key to authenticate your API requests. Keep it secret.</p>
                            
                            <div className="mt-4 flex items-center max-w-lg">
                                <input 
                                    type="text" 
                                    readOnly 
                                    value={user.api_key || 'No API key generated yet.'}
                                    className="block w-full rounded-l-md border-emerald-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm bg-white font-mono"
                                />
                                <button
                                    onClick={copyKey}
                                    className="inline-flex items-center rounded-r-md border border-l-0 border-emerald-300 bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none"
                                >
                                    {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                </button>
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
                                        &nbsp;&nbsp;-d "key={user.api_key || 'YOUR_API_KEY'}" \<br/>
                                        &nbsp;&nbsp;-d "action=services"
                                    </div>
                                </section>

                                {/* Add Order */}
                                <section>
                                    <h4 className="text-lg font-semibold text-slate-800 border-b pb-2 mb-4">2. Add Order</h4>
                                    <p className="text-sm text-slate-600 mb-2">Places a new order.</p>
                                    <div className="bg-slate-900 rounded-md p-4 text-emerald-400 font-mono text-sm overflow-x-auto">
                                        curl -X POST {apiUrl} \<br/>
                                        &nbsp;&nbsp;-d "key={user.api_key || 'YOUR_API_KEY'}" \<br/>
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
                                        &nbsp;&nbsp;-d "key={user.api_key || 'YOUR_API_KEY'}" \<br/>
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
                                        &nbsp;&nbsp;-d "key={user.api_key || 'YOUR_API_KEY'}" \<br/>
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
