<?php

namespace AhgVendor\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    // =========================================================================
    // VENDOR BROWSE / LIST / DASHBOARD
    // =========================================================================

    /**
     * Dashboard: stats, overdue transactions, active transactions, monthly chart data.
     */
    public function index(Request $request)
    {
        $stats = $this->getDashboardStats();

        $overdueTransactions = DB::table('ahg_vendor_transactions')
            ->join('ahg_vendors', 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join('ahg_vendor_service_types', 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_transactions.expected_return_date', '<', date('Y-m-d'))
            ->whereNull('ahg_vendor_transactions.actual_return_date')
            ->whereNotIn('ahg_vendor_transactions.status', ['returned', 'cancelled'])
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendors.name as vendor_name',
                'ahg_vendors.slug as vendor_slug',
                'ahg_vendor_service_types.name as service_name',
            ])
            ->orderBy('ahg_vendor_transactions.expected_return_date')
            ->get();

        $activeTransactions = DB::table('ahg_vendor_transactions')
            ->join('ahg_vendors', 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join('ahg_vendor_service_types', 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->whereNotIn('ahg_vendor_transactions.status', ['returned', 'cancelled'])
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendors.name as vendor_name',
                'ahg_vendors.slug as vendor_slug',
                'ahg_vendor_service_types.name as service_name',
            ])
            ->orderByDesc('ahg_vendor_transactions.created_at')
            ->limit(10)
            ->get();

        $statusCounts = DB::table('ahg_vendor_transactions')
            ->select(['status', DB::raw('COUNT(*) as count')])
            ->groupBy('status')
            ->get();

        $monthlyStats = DB::table('ahg_vendor_transactions')
            ->where('request_date', '>=', date('Y-m-d', strtotime('-12 months')))
            ->select([
                DB::raw('DATE_FORMAT(request_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(actual_cost) as total_cost'),
            ])
            ->groupBy(DB::raw('DATE_FORMAT(request_date, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        return view('vendor::index', [
            'stats' => $stats,
            'overdueTransactions' => $overdueTransactions,
            'activeTransactions' => $activeTransactions,
            'statusCounts' => $statusCounts,
            'monthlyStats' => $monthlyStats,
        ]);
    }

    /**
     * Browse redirects to list (main vendor browse page).
     */
    public function browse(Request $request)
    {
        return redirect()->route('ahgvendor.list', $request->query());
    }

    /**
     * List vendors with filters and sorting.
     */
    public function list(Request $request)
    {
        $filters = [
            'status' => $request->get('status'),
            'vendor_type' => $request->get('vendor_type'),
            'service_type_id' => $request->get('service_type_id'),
            'search' => $request->get('search'),
            'has_insurance' => $request->get('has_insurance'),
            'sort' => $request->get('sort', 'name'),
            'direction' => $request->get('direction', 'asc'),
        ];

        $query = DB::table('ahg_vendors')
            ->select([
                'ahg_vendors.*',
                DB::raw('(SELECT COUNT(*) FROM ahg_vendor_transactions WHERE vendor_id = ahg_vendors.id) as transaction_count'),
                DB::raw('(SELECT COUNT(*) FROM ahg_vendor_transactions WHERE vendor_id = ahg_vendors.id AND status NOT IN ("returned", "cancelled")) as active_transactions'),
            ]);

        if (!empty($filters['status'])) {
            $query->where('ahg_vendors.status', $filters['status']);
        }

        if (!empty($filters['vendor_type'])) {
            $query->where('ahg_vendors.vendor_type', $filters['vendor_type']);
        }

        if (!empty($filters['service_type_id'])) {
            $query->whereExists(function ($q) use ($filters) {
                $q->select(DB::raw(1))
                    ->from('ahg_vendor_services')
                    ->whereColumn('vendor_id', 'ahg_vendors.id')
                    ->where('service_type_id', $filters['service_type_id']);
            });
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('ahg_vendors.name', 'LIKE', $search)
                    ->orWhere('ahg_vendors.vendor_code', 'LIKE', $search)
                    ->orWhere('ahg_vendors.email', 'LIKE', $search)
                    ->orWhere('ahg_vendors.city', 'LIKE', $search);
            });
        }

        if (!empty($filters['has_insurance'])) {
            $query->where('ahg_vendors.has_insurance', 1)
                ->where('ahg_vendors.insurance_expiry_date', '>=', date('Y-m-d'));
        }

        $query->orderBy($filters['sort'], $filters['direction']);

        $vendors = $query->get();

        $serviceTypes = DB::table('ahg_vendor_service_types')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $vendorTypes = $this->loadDropdown('vendor_type');
        $vendorStatuses = $this->loadDropdown('vendor_status');

        return view('vendor::list', [
            'filters' => $filters,
            'vendors' => $vendors,
            'serviceTypes' => $serviceTypes,
            'vendorTypes' => $vendorTypes,
            'vendorStatuses' => $vendorStatuses,
        ]);
    }

    // =========================================================================
    // VENDOR CRUD
    // =========================================================================

    /**
     * Show vendor detail page.
     */
    public function view(Request $request, string $slug)
    {
        $vendor = DB::table('ahg_vendors')->where('slug', $slug)->first();

        if (!$vendor) {
            abort(404, 'Vendor not found');
        }

        $contacts = DB::table('ahg_vendor_contacts')
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        $services = DB::table('ahg_vendor_services')
            ->join('ahg_vendor_service_types', 'ahg_vendor_services.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_services.vendor_id', $vendor->id)
            ->select([
                'ahg_vendor_services.*',
                'ahg_vendor_service_types.name as service_name',
                'ahg_vendor_service_types.slug as service_slug',
            ])
            ->orderBy('ahg_vendor_service_types.display_order')
            ->get();

        $stats = $this->getVendorStats($vendor->id);

        $transactions = DB::table('ahg_vendor_transactions')
            ->join('ahg_vendor_service_types', 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_transactions.vendor_id', $vendor->id)
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendor_service_types.name as service_name',
                DB::raw('(SELECT COUNT(*) FROM ahg_vendor_transaction_items WHERE transaction_id = ahg_vendor_transactions.id) as item_count'),
            ])
            ->orderByDesc('ahg_vendor_transactions.created_at')
            ->get();

        return view('vendor::view', [
            'vendor' => $vendor,
            'contacts' => $contacts,
            'services' => $services,
            'stats' => $stats,
            'transactions' => $transactions,
        ]);
    }

    /**
     * GET/POST: Create a new vendor.
     */
    public function add(Request $request)
    {
        $form = [];
        $errors = [];
        $vendorTypes = $this->loadDropdown('vendor_type');
        $serviceTypes = DB::table('ahg_vendor_service_types')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        if ($request->isMethod('post')) {
            $data = $this->extractVendorData($request);
            $errors = $this->validateVendor($data);

            if (empty($errors)) {
                try {
                    $data['created_by'] = auth()->id();
                    $data['slug'] = $this->generateVendorSlug($data['name']);
                    $data['vendor_code'] = $data['vendor_code'] ?: $this->generateVendorCode();
                    $data['created_at'] = now();
                    $data['updated_at'] = now();

                    $vendorId = DB::table('ahg_vendors')->insertGetId($data);

                    // Sync services
                    $serviceIds = $request->input('service_ids', []);
                    foreach ($serviceIds as $serviceId) {
                        DB::table('ahg_vendor_services')->insert([
                            'vendor_id' => $vendorId,
                            'service_type_id' => (int) $serviceId,
                            'created_at' => now(),
                        ]);
                    }

                    $vendor = DB::table('ahg_vendors')->where('id', $vendorId)->first();

                    return redirect()
                        ->route('ahgvendor.view', ['slug' => $vendor->slug])
                        ->with('notice', 'Vendor created successfully');
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();
                }
            }

            $form = $data;
        }

        return view('vendor::add', [
            'form' => $form,
            'errors' => $errors,
            'vendorTypes' => $vendorTypes,
            'serviceTypes' => $serviceTypes,
        ]);
    }

    /**
     * GET/POST: Edit an existing vendor.
     */
    public function edit(Request $request, string $slug)
    {
        $vendor = DB::table('ahg_vendors')->where('slug', $slug)->first();

        if (!$vendor) {
            abort(404, 'Vendor not found');
        }

        $errors = [];
        $vendorTypes = $this->loadDropdown('vendor_type');
        $vendorStatuses = $this->loadDropdown('vendor_status');

        $serviceTypes = DB::table('ahg_vendor_service_types')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $vendorServices = DB::table('ahg_vendor_services')
            ->join('ahg_vendor_service_types', 'ahg_vendor_services.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_services.vendor_id', $vendor->id)
            ->select([
                'ahg_vendor_services.*',
                'ahg_vendor_service_types.name as service_name',
                'ahg_vendor_service_types.slug as service_slug',
            ])
            ->orderBy('ahg_vendor_service_types.display_order')
            ->get();

        if ($request->isMethod('post')) {
            $data = $this->extractVendorData($request);
            $errors = $this->validateVendor($data, $vendor->id);

            if (empty($errors)) {
                try {
                    $data['updated_at'] = now();

                    if (isset($data['name'])) {
                        $data['slug'] = $this->generateVendorSlug($data['name'], $vendor->id);
                    }

                    DB::table('ahg_vendors')->where('id', $vendor->id)->update($data);

                    // Sync services
                    $serviceIds = $request->input('service_ids', []);
                    $this->syncVendorServices($vendor->id, $serviceIds);

                    $vendor = DB::table('ahg_vendors')->where('id', $vendor->id)->first();

                    return redirect()
                        ->route('ahgvendor.view', ['slug' => $vendor->slug])
                        ->with('notice', 'Vendor updated successfully');
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();
                }
            }
        }

        return view('vendor::edit', [
            'vendor' => $vendor,
            'errors' => $errors,
            'vendorTypes' => $vendorTypes,
            'vendorStatuses' => $vendorStatuses,
            'serviceTypes' => $serviceTypes,
            'vendorServices' => $vendorServices,
        ]);
    }

    /**
     * POST: Delete a vendor (only if no active transactions).
     */
    public function delete(Request $request, string $slug)
    {
        $vendor = DB::table('ahg_vendors')->where('slug', $slug)->first();

        if (!$vendor) {
            abort(404, 'Vendor not found');
        }

        $activeTransactions = DB::table('ahg_vendor_transactions')
            ->where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['returned', 'cancelled'])
            ->count();

        if ($activeTransactions > 0) {
            return redirect()
                ->route('ahgvendor.list')
                ->with('error', 'Cannot delete vendor with active transactions');
        }

        // Remove vendor services
        DB::table('ahg_vendor_services')->where('vendor_id', $vendor->id)->delete();
        // Remove vendor contacts
        DB::table('ahg_vendor_contacts')->where('vendor_id', $vendor->id)->delete();
        // Remove the vendor
        DB::table('ahg_vendors')->where('id', $vendor->id)->delete();

        return redirect()
            ->route('ahgvendor.list')
            ->with('notice', 'Vendor deleted successfully');
    }

    // =========================================================================
    // VENDOR CONTACTS
    // =========================================================================

    /**
     * POST: Add a contact to a vendor.
     */
    public function addContact(Request $request, string $slug)
    {
        $vendor = DB::table('ahg_vendors')->where('slug', $slug)->first();

        if (!$vendor) {
            abort(404, 'Vendor not found');
        }

        $data = [
            'vendor_id' => $vendor->id,
            'name' => trim($request->input('contact_name', '')),
            'position' => $request->input('position'),
            'department' => $request->input('department'),
            'phone' => $request->input('contact_phone'),
            'mobile' => $request->input('mobile'),
            'email' => $request->input('contact_email'),
            'is_primary' => $request->input('is_primary') ? 1 : 0,
            'notes' => $request->input('contact_notes'),
            'created_at' => now(),
        ];

        if (empty($data['name'])) {
            return redirect()
                ->route('ahgvendor.view', ['slug' => $slug])
                ->with('error', 'Contact name is required');
        }

        // If marking as primary, clear other primary flags
        if ($data['is_primary']) {
            DB::table('ahg_vendor_contacts')
                ->where('vendor_id', $vendor->id)
                ->update(['is_primary' => 0]);
        }

        DB::table('ahg_vendor_contacts')->insert($data);

        return redirect()
            ->route('ahgvendor.view', ['slug' => $slug])
            ->with('notice', 'Contact added successfully');
    }

    /**
     * POST: Update a vendor contact.
     */
    public function updateContact(Request $request, string $slug, int $contactId)
    {
        $data = [
            'name' => trim($request->input('contact_name', '')),
            'position' => $request->input('position'),
            'department' => $request->input('department'),
            'phone' => $request->input('contact_phone'),
            'mobile' => $request->input('mobile'),
            'email' => $request->input('contact_email'),
            'is_primary' => $request->input('is_primary') ? 1 : 0,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'notes' => $request->input('contact_notes'),
            'updated_at' => now(),
        ];

        // If marking as primary, clear other primary flags for this vendor
        if ($data['is_primary']) {
            $contact = DB::table('ahg_vendor_contacts')->where('id', $contactId)->first();
            if ($contact) {
                DB::table('ahg_vendor_contacts')
                    ->where('vendor_id', $contact->vendor_id)
                    ->where('id', '!=', $contactId)
                    ->update(['is_primary' => 0]);
            }
        }

        DB::table('ahg_vendor_contacts')->where('id', $contactId)->update($data);

        return redirect()
            ->route('ahgvendor.view', ['slug' => $slug])
            ->with('notice', 'Contact updated');
    }

    /**
     * POST: Delete a vendor contact.
     */
    public function deleteContact(Request $request, string $slug, int $contactId)
    {
        DB::table('ahg_vendor_contacts')->where('id', $contactId)->delete();

        return redirect()
            ->route('ahgvendor.view', ['slug' => $slug])
            ->with('notice', 'Contact deleted');
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

    /**
     * Browse transactions with filters.
     */
    public function transactions(Request $request)
    {
        $filters = [
            'status' => $request->get('status'),
            'vendor_id' => $request->get('vendor_id'),
            'service_type_id' => $request->get('service_type_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'search' => $request->get('search'),
            'overdue' => $request->get('overdue'),
        ];

        $query = DB::table('ahg_vendor_transactions')
            ->join('ahg_vendors', 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join('ahg_vendor_service_types', 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendors.name as vendor_name',
                'ahg_vendors.slug as vendor_slug',
                'ahg_vendor_service_types.name as service_name',
                DB::raw('(SELECT COUNT(*) FROM ahg_vendor_transaction_items WHERE transaction_id = ahg_vendor_transactions.id) as item_count'),
            ]);

        if (!empty($filters['status'])) {
            $query->where('ahg_vendor_transactions.status', $filters['status']);
        }

        if (!empty($filters['vendor_id'])) {
            $query->where('ahg_vendor_transactions.vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['service_type_id'])) {
            $query->where('ahg_vendor_transactions.service_type_id', $filters['service_type_id']);
        }

        if (!empty($filters['overdue'])) {
            $query->where('ahg_vendor_transactions.expected_return_date', '<', date('Y-m-d'))
                ->whereNull('ahg_vendor_transactions.actual_return_date')
                ->whereNotIn('ahg_vendor_transactions.status', ['returned', 'cancelled']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('ahg_vendor_transactions.request_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('ahg_vendor_transactions.request_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('ahg_vendor_transactions.transaction_number', 'LIKE', $search)
                    ->orWhere('ahg_vendors.name', 'LIKE', $search);
            });
        }

        $query->orderByDesc('ahg_vendor_transactions.created_at');
        $transactions = $query->get();

        $vendors = DB::table('ahg_vendors')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $serviceTypes = DB::table('ahg_vendor_service_types')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $statusOptions = $this->loadDropdown('vendor_transaction_status');

        return view('vendor::transactions', [
            'filters' => $filters,
            'transactions' => $transactions,
            'vendors' => $vendors,
            'serviceTypes' => $serviceTypes,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Show a single transaction detail page.
     */
    public function viewTransaction(Request $request, int $id)
    {
        $transaction = DB::table('ahg_vendor_transactions')
            ->join('ahg_vendors', 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join('ahg_vendor_service_types', 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_transactions.id', $id)
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendors.name as vendor_name',
                'ahg_vendors.slug as vendor_slug',
                'ahg_vendors.email as vendor_email',
                'ahg_vendors.phone as vendor_phone',
                'ahg_vendor_service_types.name as service_name',
                'ahg_vendor_service_types.requires_insurance',
                'ahg_vendor_service_types.requires_valuation',
            ])
            ->first();

        if (!$transaction) {
            abort(404, 'Transaction not found');
        }

        $items = DB::table('ahg_vendor_transaction_items')
            ->leftJoin('information_object', 'ahg_vendor_transaction_items.information_object_id', '=', 'information_object.id')
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('ahg_vendor_transaction_items.transaction_id', $id)
            ->select([
                'ahg_vendor_transaction_items.*',
                'information_object.identifier',
                'slug.slug as io_slug',
                'information_object_i18n.title as io_title',
            ])
            ->get();

        $history = DB::table('ahg_vendor_transaction_history')
            ->leftJoin('user', 'ahg_vendor_transaction_history.changed_by', '=', 'user.id')
            ->where('ahg_vendor_transaction_history.transaction_id', $id)
            ->select([
                'ahg_vendor_transaction_history.*',
                'user.username as changed_by_name',
            ])
            ->orderByDesc('ahg_vendor_transaction_history.created_at')
            ->get();

        $attachments = DB::table('ahg_vendor_transaction_attachments')
            ->where('transaction_id', $id)
            ->orderBy('created_at')
            ->get();

        $statusOptions = $this->loadDropdown('vendor_transaction_status');
        $conditionRatings = $this->loadDropdown('condition_grade');

        return view('vendor::view-transaction', [
            'transaction' => $transaction,
            'items' => $items,
            'history' => $history,
            'attachments' => $attachments,
            'statusOptions' => $statusOptions,
            'conditionRatings' => $conditionRatings,
        ]);
    }

    /**
     * GET/POST: Create a new transaction.
     */
    public function addTransaction(Request $request)
    {
        $form = [];
        $errors = [];

        $vendors = DB::table('ahg_vendors')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $serviceTypes = DB::table('ahg_vendor_service_types')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $conditionRatings = $this->loadDropdown('condition_grade');

        // Pre-select vendor if passed via query string
        $preselectedVendorSlug = $request->get('vendor');
        if ($preselectedVendorSlug) {
            $vendor = DB::table('ahg_vendors')->where('slug', $preselectedVendorSlug)->first();
            if ($vendor) {
                $form['vendor_id'] = $vendor->id;
            }
        }

        if ($request->isMethod('post')) {
            $data = $this->extractTransactionData($request);
            $errors = $this->validateTransaction($data);

            if (empty($errors)) {
                try {
                    $data['requested_by'] = auth()->id();
                    $data['status'] = $data['status'] ?? 'pending_approval';
                    $data['transaction_number'] = $this->generateTransactionNumber();
                    $data['created_at'] = now();
                    $data['updated_at'] = now();

                    $transactionId = DB::table('ahg_vendor_transactions')->insertGetId($data);

                    // Log initial history
                    DB::table('ahg_vendor_transaction_history')->insert([
                        'transaction_id' => $transactionId,
                        'status_from' => null,
                        'status_to' => $data['status'],
                        'changed_by' => auth()->id(),
                        'notes' => 'Transaction created',
                        'created_at' => now(),
                    ]);

                    // Add items
                    $informationObjectIds = $request->input('information_object_ids', []);
                    $conditions = $request->input('conditions', []);
                    $values = $request->input('declared_values', []);
                    $ratings = $request->input('condition_ratings', []);

                    foreach ($informationObjectIds as $index => $ioId) {
                        if (!empty($ioId)) {
                            $this->addTransactionItemRecord(
                                $transactionId,
                                (int) $ioId,
                                $conditions[$index] ?? null,
                                $ratings[$index] ?? null,
                                $values[$index] ?? null
                            );
                        }
                    }

                    return redirect()
                        ->route('ahgvendor.view-transaction', ['id' => $transactionId])
                        ->with('notice', 'Transaction created successfully');
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();
                }
            }

            $form = $data;
        }

        return view('vendor::add-transaction', [
            'form' => $form,
            'errors' => $errors,
            'vendors' => $vendors,
            'serviceTypes' => $serviceTypes,
            'conditionRatings' => $conditionRatings,
        ]);
    }

    /**
     * GET/POST: Edit a transaction.
     */
    public function editTransaction(Request $request, int $id)
    {
        $transaction = DB::table('ahg_vendor_transactions')
            ->join('ahg_vendors', 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join('ahg_vendor_service_types', 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_transactions.id', $id)
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendors.name as vendor_name',
                'ahg_vendors.slug as vendor_slug',
                'ahg_vendor_service_types.name as service_name',
            ])
            ->first();

        if (!$transaction) {
            abort(404, 'Transaction not found');
        }

        $errors = [];

        $vendors = DB::table('ahg_vendors')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $serviceTypes = DB::table('ahg_vendor_service_types')
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $paymentStatuses = $this->loadDropdown('payment_status');
        $statusOptions = $this->loadDropdown('vendor_transaction_status');

        if ($request->isMethod('post')) {
            $data = $this->extractTransactionData($request);

            try {
                $oldStatus = $transaction->status;
                $data['updated_at'] = now();

                // If status changed, log history
                if (isset($data['status']) && $data['status'] !== $oldStatus) {
                    $this->logTransactionStatusChange($id, $oldStatus, $data['status'], auth()->id());
                }

                DB::table('ahg_vendor_transactions')->where('id', $id)->update($data);

                return redirect()
                    ->route('ahgvendor.view-transaction', ['id' => $id])
                    ->with('notice', 'Transaction updated successfully');
            } catch (\Exception $e) {
                $errors['general'] = $e->getMessage();
            }
        }

        return view('vendor::edit-transaction', [
            'transaction' => $transaction,
            'errors' => $errors,
            'vendors' => $vendors,
            'serviceTypes' => $serviceTypes,
            'paymentStatuses' => $paymentStatuses,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * POST: Update transaction status (inline from view page).
     */
    public function updateTransactionStatus(Request $request, int $id)
    {
        $transaction = DB::table('ahg_vendor_transactions')->where('id', $id)->first();

        if (!$transaction) {
            abort(404, 'Transaction not found');
        }

        $status = $request->input('status');
        $notes = $request->input('notes');
        $validStatuses = array_keys($this->loadDropdown('vendor_transaction_status'));

        if (!in_array($status, $validStatuses)) {
            return redirect()
                ->route('ahgvendor.view-transaction', ['id' => $id])
                ->with('error', 'Invalid status');
        }

        $updateData = [
            'status' => $status,
            'updated_at' => now(),
        ];

        switch ($status) {
            case 'approved':
                $updateData['approval_date'] = date('Y-m-d');
                $updateData['approved_by'] = auth()->id();
                break;
            case 'dispatched':
                $updateData['dispatch_date'] = date('Y-m-d');
                $updateData['dispatched_by'] = auth()->id();
                break;
            case 'returned':
                $updateData['actual_return_date'] = date('Y-m-d');
                $updateData['received_by'] = auth()->id();
                break;
        }

        DB::table('ahg_vendor_transactions')->where('id', $id)->update($updateData);

        $this->logTransactionStatusChange($id, $transaction->status, $status, auth()->id(), $notes);

        return redirect()
            ->route('ahgvendor.view-transaction', ['id' => $id])
            ->with('notice', 'Status updated successfully');
    }

    // =========================================================================
    // TRANSACTION ITEMS
    // =========================================================================

    /**
     * POST: Add an item to a transaction.
     */
    public function addTransactionItem(Request $request, int $transactionId)
    {
        $ioId = (int) $request->input('information_object_id');

        if (empty($ioId)) {
            return redirect()
                ->route('ahgvendor.view-transaction', ['id' => $transactionId])
                ->with('error', 'Information object is required');
        }

        $this->addTransactionItemRecord(
            $transactionId,
            $ioId,
            $request->input('condition_before'),
            $request->input('condition_before_rating'),
            $request->input('declared_value'),
            $request->input('service_description')
        );

        return redirect()
            ->route('ahgvendor.view-transaction', ['id' => $transactionId])
            ->with('notice', 'Item added to transaction');
    }

    /**
     * POST: Update a transaction item (condition after, service notes, etc.).
     */
    public function updateTransactionItem(Request $request, int $transactionId, int $itemId)
    {
        $data = [
            'condition_after' => $request->input('condition_after'),
            'condition_after_rating' => $request->input('condition_after_rating'),
            'service_completed' => $request->input('service_completed') ? 1 : 0,
            'service_notes' => $request->input('service_notes'),
            'item_cost' => $request->input('item_cost') ?: null,
            'updated_at' => now(),
        ];

        DB::table('ahg_vendor_transaction_items')->where('id', $itemId)->update($data);

        return redirect()
            ->route('ahgvendor.view-transaction', ['id' => $transactionId])
            ->with('notice', 'Item updated');
    }

    /**
     * POST: Remove an item from a transaction.
     */
    public function removeTransactionItem(Request $request, int $transactionId, int $itemId)
    {
        DB::table('ahg_vendor_transaction_items')->where('id', $itemId)->delete();

        return redirect()
            ->route('ahgvendor.view-transaction', ['id' => $transactionId])
            ->with('notice', 'Item removed from transaction');
    }

    // =========================================================================
    // SERVICE TYPES MANAGEMENT
    // =========================================================================

    /**
     * GET/POST: Manage vendor service types (add, edit, delete via form_action).
     */
    public function serviceTypes(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $id = $request->input('id');
            $name = trim($request->input('name', ''));
            $description = trim($request->input('description', ''));
            $isActive = $request->input('is_active') ? 1 : 0;

            try {
                switch ($action) {
                    case 'add':
                        if (empty($name)) {
                            return redirect()
                                ->route('ahgvendor.service-types')
                                ->with('error', 'Name is required.');
                        }
                        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
                        $maxOrder = DB::table('ahg_vendor_service_types')->max('display_order') ?? 0;
                        DB::table('ahg_vendor_service_types')->insert([
                            'name' => $name,
                            'slug' => $slug,
                            'description' => $description ?: null,
                            'is_active' => $isActive,
                            'display_order' => $maxOrder + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        return redirect()
                            ->route('ahgvendor.service-types')
                            ->with('success', 'Service type added successfully.');

                    case 'edit':
                        if (empty($id) || empty($name)) {
                            return redirect()
                                ->route('ahgvendor.service-types')
                                ->with('error', 'Invalid request.');
                        }
                        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
                        DB::table('ahg_vendor_service_types')->where('id', $id)->update([
                            'name' => $name,
                            'slug' => $slug,
                            'description' => $description ?: null,
                            'is_active' => $isActive,
                            'updated_at' => now(),
                        ]);
                        return redirect()
                            ->route('ahgvendor.service-types')
                            ->with('success', 'Service type updated successfully.');

                    case 'delete':
                        if (empty($id)) {
                            return redirect()
                                ->route('ahgvendor.service-types')
                                ->with('error', 'Invalid request.');
                        }
                        DB::table('ahg_vendor_service_types')->where('id', $id)->delete();
                        return redirect()
                            ->route('ahgvendor.service-types')
                            ->with('success', 'Service type deleted successfully.');
                }
            } catch (\Exception $e) {
                return redirect()
                    ->route('ahgvendor.service-types')
                    ->with('error', 'Error: ' . $e->getMessage());
            }

            return redirect()->route('ahgvendor.service-types');
        }

        // GET: show all service types (including inactive)
        $serviceTypes = DB::table('ahg_vendor_service_types')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('vendor::service-types', [
            'serviceTypes' => $serviceTypes,
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Extract vendor form data from request.
     */
    private function extractVendorData(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'vendor_type' => $request->input('vendor_type'),
            'registration_number' => $request->input('registration_number'),
            'vat_number' => $request->input('vat_number'),
            'street_address' => $request->input('street_address'),
            'city' => $request->input('city'),
            'province' => $request->input('province'),
            'postal_code' => $request->input('postal_code'),
            'country' => $request->input('country', 'South Africa'),
            'phone' => $request->input('phone'),
            'phone_alt' => $request->input('phone_alt'),
            'fax' => $request->input('fax'),
            'email' => $request->input('email'),
            'website' => $request->input('website'),
            'bank_name' => $request->input('bank_name'),
            'bank_branch' => $request->input('bank_branch'),
            'bank_account_number' => $request->input('bank_account_number'),
            'bank_branch_code' => $request->input('bank_branch_code'),
            'bank_account_type' => $request->input('bank_account_type'),
            'has_insurance' => $request->input('has_insurance') ? 1 : 0,
            'insurance_provider' => $request->input('insurance_provider'),
            'insurance_policy_number' => $request->input('insurance_policy_number'),
            'insurance_expiry_date' => $request->input('insurance_expiry_date') ?: null,
            'insurance_coverage_amount' => $request->input('insurance_coverage_amount') ?: null,
            'status' => $request->input('status', 'active'),
            'vendor_code' => $request->input('vendor_code'),
            'is_preferred' => $request->input('is_preferred') ? 1 : 0,
            'is_bbbee_compliant' => $request->input('is_bbbee_compliant') ? 1 : 0,
            'notes' => $request->input('notes'),
        ];
    }

    /**
     * Validate vendor data. Returns array of errors (empty = valid).
     */
    private function validateVendor(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Vendor name is required';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        return $errors;
    }

    /**
     * Extract transaction form data from request.
     */
    private function extractTransactionData(Request $request): array
    {
        return [
            'vendor_id' => $request->input('vendor_id'),
            'service_type_id' => $request->input('service_type_id'),
            'request_date' => $request->input('request_date'),
            'expected_return_date' => $request->input('expected_return_date') ?: null,
            'estimated_cost' => $request->input('estimated_cost') ?: null,
            'actual_cost' => $request->input('actual_cost') ?: null,
            'quote_reference' => $request->input('quote_reference'),
            'invoice_reference' => $request->input('invoice_reference'),
            'invoice_date' => $request->input('invoice_date') ?: null,
            'payment_status' => $request->input('payment_status', 'pending'),
            'total_insured_value' => $request->input('total_insured_value') ?: null,
            'insurance_arranged' => $request->input('insurance_arranged') ? 1 : 0,
            'insurance_reference' => $request->input('insurance_reference'),
            'shipping_method' => $request->input('shipping_method'),
            'tracking_number' => $request->input('tracking_number'),
            'courier_company' => $request->input('courier_company'),
            'dispatch_notes' => $request->input('dispatch_notes'),
            'internal_notes' => $request->input('internal_notes'),
        ];
    }

    /**
     * Validate transaction data. Returns array of errors (empty = valid).
     */
    private function validateTransaction(array $data): array
    {
        $errors = [];

        if (empty($data['vendor_id'])) {
            $errors['vendor_id'] = 'Vendor is required';
        }

        if (empty($data['service_type_id'])) {
            $errors['service_type_id'] = 'Service type is required';
        }

        if (empty($data['request_date'])) {
            $errors['request_date'] = 'Request date is required';
        }

        return $errors;
    }

    /**
     * Sync vendor services: add new, remove deleted.
     */
    private function syncVendorServices(int $vendorId, array $serviceIds): void
    {
        $currentIds = DB::table('ahg_vendor_services')
            ->where('vendor_id', $vendorId)
            ->pluck('service_type_id')
            ->toArray();

        // Add new
        foreach ($serviceIds as $serviceId) {
            if (!in_array((int) $serviceId, $currentIds)) {
                DB::table('ahg_vendor_services')->insert([
                    'vendor_id' => $vendorId,
                    'service_type_id' => (int) $serviceId,
                    'created_at' => now(),
                ]);
            }
        }

        // Remove old
        $serviceIdsInt = array_map('intval', $serviceIds);
        foreach ($currentIds as $currentId) {
            if (!in_array($currentId, $serviceIdsInt)) {
                DB::table('ahg_vendor_services')
                    ->where('vendor_id', $vendorId)
                    ->where('service_type_id', $currentId)
                    ->delete();
            }
        }
    }

    /**
     * Add a transaction item record (used by addTransaction and addTransactionItem).
     */
    private function addTransactionItemRecord(
        int $transactionId,
        int $informationObjectId,
        ?string $conditionBefore = null,
        ?string $conditionBeforeRating = null,
        ?string $declaredValue = null,
        ?string $serviceDescription = null
    ): int {
        $io = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('information_object.id', $informationObjectId)
            ->select(['information_object.identifier', 'information_object_i18n.title'])
            ->first();

        return DB::table('ahg_vendor_transaction_items')->insertGetId([
            'transaction_id' => $transactionId,
            'information_object_id' => $informationObjectId,
            'item_title' => $io->title ?? null,
            'item_reference' => $io->identifier ?? null,
            'condition_before' => $conditionBefore,
            'condition_before_rating' => $conditionBeforeRating,
            'declared_value' => $declaredValue ?: null,
            'service_description' => $serviceDescription,
            'created_at' => now(),
        ]);
    }

    /**
     * Log a transaction status change to the history table.
     */
    private function logTransactionStatusChange(int $transactionId, ?string $statusFrom, string $statusTo, int $userId, ?string $notes = null): void
    {
        DB::table('ahg_vendor_transaction_history')->insert([
            'transaction_id' => $transactionId,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'changed_by' => $userId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    /**
     * Generate a unique vendor slug from the name.
     */
    private function generateVendorSlug(string $name, ?int $excludeId = null): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = DB::table('ahg_vendors')->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Generate a unique vendor code (VNDyyNNNN).
     */
    private function generateVendorCode(): string
    {
        $prefix = 'VND';
        $year = date('y');
        $lastCode = DB::table('ahg_vendors')
            ->where('vendor_code', 'LIKE', "{$prefix}{$year}%")
            ->orderBy('vendor_code', 'desc')
            ->value('vendor_code');

        if ($lastCode) {
            $num = (int) substr($lastCode, -4) + 1;
        } else {
            $num = 1;
        }

        return $prefix . $year . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique transaction number (TXN-YYYYMM-NNNN).
     */
    private function generateTransactionNumber(): string
    {
        $prefix = 'TXN';
        $year = date('Y');
        $month = date('m');

        $lastNumber = DB::table('ahg_vendor_transactions')
            ->where('transaction_number', 'LIKE', "{$prefix}-{$year}{$month}%")
            ->orderBy('transaction_number', 'desc')
            ->value('transaction_number');

        if ($lastNumber) {
            $num = (int) substr($lastNumber, -4) + 1;
        } else {
            $num = 1;
        }

        return $prefix . '-' . $year . $month . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get dashboard statistics.
     */
    private function getDashboardStats(): array
    {
        $today = date('Y-m-d');

        return [
            'active_vendors' => DB::table('ahg_vendors')->where('status', 'active')->count(),
            'active_transactions' => DB::table('ahg_vendor_transactions')
                ->whereNotIn('status', ['returned', 'cancelled'])
                ->count(),
            'overdue_items' => DB::table('ahg_vendor_transactions')
                ->where('expected_return_date', '<', $today)
                ->whereNull('actual_return_date')
                ->whereNotIn('status', ['returned', 'cancelled'])
                ->count(),
            'pending_approval' => DB::table('ahg_vendor_transactions')
                ->where('status', 'pending_approval')
                ->count(),
            'items_out' => DB::table('ahg_vendor_transaction_items')
                ->join('ahg_vendor_transactions', 'ahg_vendor_transaction_items.transaction_id', '=', 'ahg_vendor_transactions.id')
                ->whereNotIn('ahg_vendor_transactions.status', ['returned', 'cancelled'])
                ->count(),
            'this_month_cost' => DB::table('ahg_vendor_transactions')
                ->whereYear('request_date', date('Y'))
                ->whereMonth('request_date', date('m'))
                ->sum('actual_cost') ?? 0,
        ];
    }

    /**
     * Get per-vendor statistics.
     */
    private function getVendorStats(int $vendorId): object
    {
        $stats = DB::table('ahg_vendor_transactions')
            ->where('vendor_id', $vendorId)
            ->select([
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(CASE WHEN status = "returned" THEN 1 ELSE 0 END) as completed_transactions'),
                DB::raw('SUM(CASE WHEN status NOT IN ("returned", "cancelled") THEN 1 ELSE 0 END) as active_transactions'),
                DB::raw('SUM(CASE WHEN actual_return_date <= expected_return_date THEN 1 ELSE 0 END) as on_time_count'),
                DB::raw('SUM(actual_cost) as total_cost'),
                DB::raw('AVG(DATEDIFF(actual_return_date, dispatch_date)) as avg_turnaround'),
            ])
            ->first();

        $stats->items_handled = DB::table('ahg_vendor_transaction_items')
            ->join('ahg_vendor_transactions', 'ahg_vendor_transaction_items.transaction_id', '=', 'ahg_vendor_transactions.id')
            ->where('ahg_vendor_transactions.vendor_id', $vendorId)
            ->count();

        return $stats;
    }

    /**
     * Load dropdown values from ahg_dropdown table.
     * Returns [code => label] associative array.
     */
    private function loadDropdown(string $taxonomy): array
    {
        $rows = DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['code', 'label']);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->code] = $row->label;
        }

        return $result;
    }
}
