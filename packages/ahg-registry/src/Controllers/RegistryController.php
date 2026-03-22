<?php

namespace AhgRegistry\Controllers;

use AhgRegistry\Services\RegistryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RegistryController extends Controller
{
    public function __construct(private RegistryService $service) {}

    /* ================================================================== */
    /*  INDEX / DASHBOARD                                                 */
    /* ================================================================== */

    public function index()
    {
        $stats                  = $this->service->getStats();
        $featuredInstitutions   = $this->service->getFeaturedInstitutions();
        $featuredVendors        = $this->service->getFeaturedVendors();
        $featuredSoftware       = $this->service->getFeaturedSoftware();
        $recentBlog             = $this->service->getRecentBlogPosts();
        $recentDiscussions      = $this->service->getRecentDiscussions();

        return view('ahg-registry::index', compact(
            'stats', 'featuredInstitutions', 'featuredVendors',
            'featuredSoftware', 'recentBlog', 'recentDiscussions',
        ));
    }

    public function search(Request $request)
    {
        $q = $request->input('q', '');
        return view('ahg-registry::search', compact('q'));
    }

    public function map()
    {
        return view('ahg-registry::map');
    }

    /* ================================================================== */
    /*  INSTITUTIONS                                                      */
    /* ================================================================== */

    public function institutionBrowse(Request $request)
    {
        $filters = $request->only(['q', 'country', 'type']);
        $page    = max(1, (int) $request->input('page', 1));
        $result  = $this->service->browseInstitutions($filters, $page);
        return view('ahg-registry::institution-browse', compact('result', 'filters'));
    }

    public function institutionView(int $id)
    {
        $institution = $this->service->getInstitution($id);
        abort_unless($institution, 404);
        return view('ahg-registry::institution-view', compact('institution'));
    }

    public function institutionRegister()
    {
        return view('ahg-registry::institution-register');
    }

    public function institutionRegisterStore(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        session()->flash('success', __('Institution registered successfully.'));
        return redirect()->route('registry.institutionBrowse');
    }

    public function institutionEdit(int $id)
    {
        $institution = $this->service->getInstitution($id);
        abort_unless($institution, 404);
        return view('ahg-registry::institution-edit', compact('institution'));
    }

    public function institutionSoftware(int $id)
    {
        $institution = $this->service->getInstitution($id);
        abort_unless($institution, 404);
        return view('ahg-registry::institution-software', compact('institution'));
    }

    public function institutionVendors(int $id)
    {
        $institution = $this->service->getInstitution($id);
        abort_unless($institution, 404);
        return view('ahg-registry::institution-vendors', compact('institution'));
    }

    /* ================================================================== */
    /*  VENDORS                                                           */
    /* ================================================================== */

    public function vendorBrowse(Request $request)
    {
        $filters = $request->only(['q']);
        $page    = max(1, (int) $request->input('page', 1));
        $result  = $this->service->browseVendors($filters, $page);
        return view('ahg-registry::vendor-browse', compact('result', 'filters'));
    }

    public function vendorView(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-view', compact('vendor'));
    }

    public function vendorRegister()
    {
        return view('ahg-registry::vendor-register');
    }

    public function vendorEdit(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-edit', compact('vendor'));
    }

    public function vendorClients(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-clients', compact('vendor'));
    }

    public function vendorClientForm(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-client-form', compact('vendor'));
    }

    public function vendorSoftwareManage(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-software-manage', compact('vendor'));
    }

    public function vendorSoftwareForm(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-software-form', compact('vendor'));
    }

    public function vendorSoftwareUpload(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-software-upload', compact('vendor'));
    }

    public function vendorReleaseManage(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-release-manage', compact('vendor'));
    }

    public function vendorReleaseForm(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-release-form', compact('vendor'));
    }

    public function vendorCallLogForm(int $id)
    {
        $vendor = $this->service->getVendor($id);
        abort_unless($vendor, 404);
        return view('ahg-registry::vendor-call-log-form', compact('vendor'));
    }

    /* ================================================================== */
    /*  SOFTWARE                                                          */
    /* ================================================================== */

    public function softwareBrowse(Request $request)
    {
        $filters = $request->only(['q']);
        $page    = max(1, (int) $request->input('page', 1));
        $result  = $this->service->browseSoftware($filters, $page);
        return view('ahg-registry::software-browse', compact('result', 'filters'));
    }

    public function softwareView(int $id)
    {
        $software = $this->service->getSoftware($id);
        abort_unless($software, 404);
        return view('ahg-registry::software-view', compact('software'));
    }

    public function softwareReleases(int $id)
    {
        $software = $this->service->getSoftware($id);
        abort_unless($software, 404);
        return view('ahg-registry::software-releases', compact('software'));
    }

    public function softwareComponents(int $id)
    {
        $software = $this->service->getSoftware($id);
        abort_unless($software, 404);
        return view('ahg-registry::software-components', compact('software'));
    }

    public function softwareComponentAdd(int $id)
    {
        $software = $this->service->getSoftware($id);
        abort_unless($software, 404);
        return view('ahg-registry::software-component-add', compact('software'));
    }

    public function softwareComponentEdit(int $id, int $componentId)
    {
        $software = $this->service->getSoftware($id);
        abort_unless($software, 404);
        return view('ahg-registry::software-component-edit', compact('software', 'componentId'));
    }

    /* ================================================================== */
    /*  STANDARDS                                                         */
    /* ================================================================== */

    public function standardBrowse(Request $request)
    {
        $filters = $request->only(['q']);
        $page    = max(1, (int) $request->input('page', 1));
        $result  = $this->service->browseStandards($filters, $page);
        return view('ahg-registry::standard-browse', compact('result', 'filters'));
    }

    public function standardView(int $id)
    {
        return view('ahg-registry::standard-view', compact('id'));
    }

    public function standardsSchema()
    {
        return view('ahg-registry::standards-schema');
    }

    /* ================================================================== */
    /*  GROUPS                                                            */
    /* ================================================================== */

    public function groupBrowse(Request $request)
    {
        $filters = $request->only(['q']);
        $page    = max(1, (int) $request->input('page', 1));
        $result  = $this->service->browseGroups($filters, $page);
        return view('ahg-registry::group-browse', compact('result', 'filters'));
    }

    public function groupView(int $id)
    {
        return view('ahg-registry::group-view', compact('id'));
    }

    public function groupCreate()
    {
        return view('ahg-registry::group-create');
    }

    public function groupEdit(int $id)
    {
        return view('ahg-registry::group-edit', compact('id'));
    }

    public function groupMembers(int $id)
    {
        return view('ahg-registry::group-members', compact('id'));
    }

    public function groupMembersManage(int $id)
    {
        return view('ahg-registry::group-members-manage', compact('id'));
    }

    /* ================================================================== */
    /*  BLOG                                                              */
    /* ================================================================== */

    public function blogList(Request $request)
    {
        $filters = $request->only(['q', 'category']);
        $page    = max(1, (int) $request->input('page', 1));
        $result  = $this->service->browseBlog($filters, $page);
        return view('ahg-registry::blog-list', compact('result', 'filters'));
    }

    public function blogView(string $slug)
    {
        return view('ahg-registry::blog-view', compact('slug'));
    }

    public function blogNew()
    {
        return view('ahg-registry::blog-new');
    }

    public function blogEdit(int $id)
    {
        return view('ahg-registry::blog-edit', compact('id'));
    }

    public function blogForm(int $id = null)
    {
        return view('ahg-registry::blog-form', compact('id'));
    }

    public function myBlog()
    {
        return view('ahg-registry::my-blog');
    }

    /* ================================================================== */
    /*  COMMUNITY / DISCUSSIONS                                           */
    /* ================================================================== */

    public function community()
    {
        return view('ahg-registry::community');
    }

    public function discussionList(Request $request)
    {
        $filters = $request->only(['q']);
        $page    = max(1, (int) $request->input('page', 1));
        $result  = $this->service->browseDiscussions($filters, $page);
        return view('ahg-registry::discussion-list', compact('result', 'filters'));
    }

    public function discussionView(int $id)
    {
        return view('ahg-registry::discussion-view', compact('id'));
    }

    public function discussionNew()
    {
        return view('ahg-registry::discussion-new');
    }

    public function discussionReply(int $id)
    {
        return view('ahg-registry::discussion-reply', compact('id'));
    }

    /* ================================================================== */
    /*  ERD                                                               */
    /* ================================================================== */

    public function erdBrowse(Request $request)
    {
        $page   = max(1, (int) $request->input('page', 1));
        $result = $this->service->browseErd($page);
        return view('ahg-registry::erd-browse', compact('result'));
    }

    public function erdView(int $id)
    {
        return view('ahg-registry::erd-view', compact('id'));
    }

    /* ================================================================== */
    /*  SETUP GUIDES                                                      */
    /* ================================================================== */

    public function setupGuideBrowse(Request $request)
    {
        $page   = max(1, (int) $request->input('page', 1));
        $result = $this->service->browseSetupGuides($page);
        return view('ahg-registry::setup-guide-browse', compact('result'));
    }

    public function setupGuideView(int $id)
    {
        return view('ahg-registry::setup-guide-view', compact('id'));
    }

    /* ================================================================== */
    /*  NEWSLETTERS                                                       */
    /* ================================================================== */

    public function newsletterBrowse(Request $request)
    {
        $page   = max(1, (int) $request->input('page', 1));
        $result = $this->service->browseNewsletters($page);
        return view('ahg-registry::newsletter-browse', compact('result'));
    }

    public function newsletterView(int $id)
    {
        return view('ahg-registry::newsletter-view', compact('id'));
    }

    public function newsletterSubscribe()
    {
        return view('ahg-registry::newsletter-subscribe');
    }

    public function newsletterUnsubscribe()
    {
        return view('ahg-registry::newsletter-unsubscribe');
    }

    /* ================================================================== */
    /*  REVIEWS                                                           */
    /* ================================================================== */

    public function reviewForm(Request $request)
    {
        return view('ahg-registry::review-form');
    }

    /* ================================================================== */
    /*  MY INSTITUTION                                                    */
    /* ================================================================== */

    public function myInstitutionDashboard()
    {
        return view('ahg-registry::my-institution-dashboard');
    }

    public function myInstitutionContacts()
    {
        return view('ahg-registry::my-institution-contacts');
    }

    public function myInstitutionContactAdd()
    {
        return view('ahg-registry::my-institution-contact-add');
    }

    public function myInstitutionContactEdit(int $id)
    {
        return view('ahg-registry::my-institution-contact-edit', compact('id'));
    }

    public function myInstitutionInstances()
    {
        return view('ahg-registry::my-institution-instances');
    }

    public function myInstitutionInstanceAdd()
    {
        return view('ahg-registry::my-institution-instance-add');
    }

    public function myInstitutionInstanceEdit(int $id)
    {
        return view('ahg-registry::my-institution-instance-edit', compact('id'));
    }

    public function myInstitutionSoftware()
    {
        return view('ahg-registry::my-institution-software');
    }

    public function myInstitutionVendors()
    {
        return view('ahg-registry::my-institution-vendors');
    }

    public function myInstitutionReview()
    {
        return view('ahg-registry::my-institution-review');
    }

    /* ================================================================== */
    /*  MY VENDOR                                                         */
    /* ================================================================== */

    public function myVendorDashboard()
    {
        return view('ahg-registry::my-vendor-dashboard');
    }

    public function myVendorContacts()
    {
        return view('ahg-registry::my-vendor-contacts');
    }

    public function myVendorContactAdd()
    {
        return view('ahg-registry::my-vendor-contact-add');
    }

    public function myVendorContactEdit(int $id)
    {
        return view('ahg-registry::my-vendor-contact-edit', compact('id'));
    }

    public function myVendorClients()
    {
        return view('ahg-registry::my-vendor-clients');
    }

    public function myVendorClientAdd()
    {
        return view('ahg-registry::my-vendor-client-add');
    }

    public function myVendorSoftware()
    {
        return view('ahg-registry::my-vendor-software');
    }

    public function myVendorSoftwareAdd()
    {
        return view('ahg-registry::my-vendor-software-add');
    }

    public function myVendorSoftwareEdit(int $id)
    {
        return view('ahg-registry::my-vendor-software-edit', compact('id'));
    }

    public function myVendorSoftwareReleases(int $id)
    {
        return view('ahg-registry::my-vendor-software-releases', compact('id'));
    }

    public function myVendorSoftwareReleaseAdd(int $id)
    {
        return view('ahg-registry::my-vendor-software-release-add', compact('id'));
    }

    public function myVendorSoftwareUpload(int $id)
    {
        return view('ahg-registry::my-vendor-software-upload', compact('id'));
    }

    public function myVendorCallLog()
    {
        return view('ahg-registry::my-vendor-call-log');
    }

    public function myVendorCallLogView(int $id)
    {
        return view('ahg-registry::my-vendor-call-log-view', compact('id'));
    }

    /* ================================================================== */
    /*  CONTACTS / INSTANCES (generic)                                    */
    /* ================================================================== */

    public function contactForm()
    {
        return view('ahg-registry::contact-form');
    }

    public function contactsManage(int $parentId)
    {
        return view('ahg-registry::contacts-manage', compact('parentId'));
    }

    public function instancesManage(int $parentId)
    {
        return view('ahg-registry::instances-manage', compact('parentId'));
    }

    public function instanceView(int $id)
    {
        return view('ahg-registry::instance-view', compact('id'));
    }

    public function instanceForm(int $id = null)
    {
        return view('ahg-registry::instance-form', compact('id'));
    }

    public function instanceRelink(int $id)
    {
        return view('ahg-registry::instance-relink', compact('id'));
    }

    /* ================================================================== */
    /*  FAVORITES                                                         */
    /* ================================================================== */

    public function myFavorites()
    {
        return view('ahg-registry::my-favorites');
    }

    public function myGroups()
    {
        return view('ahg-registry::my-groups');
    }

    /* ================================================================== */
    /*  AUTH                                                               */
    /* ================================================================== */

    public function login()
    {
        return view('ahg-registry::login');
    }

    public function register()
    {
        return view('ahg-registry::register');
    }

    /* ================================================================== */
    /*  ADMIN                                                             */
    /* ================================================================== */

    public function adminDashboard()
    {
        return view('ahg-registry::admin.dashboard');
    }

    public function adminUsers()
    {
        return view('ahg-registry::admin.users');
    }

    public function adminUserEdit(int $id)
    {
        return view('ahg-registry::admin.user-edit', compact('id'));
    }

    public function adminUserManage(int $id)
    {
        return view('ahg-registry::admin.user-manage', compact('id'));
    }

    public function adminGroups()
    {
        return view('ahg-registry::admin.groups');
    }

    public function adminGroupEdit(int $id)
    {
        return view('ahg-registry::admin.group-edit', compact('id'));
    }

    public function adminGroupMembers(int $id)
    {
        return view('ahg-registry::admin.group-members', compact('id'));
    }

    public function adminInstitutions()
    {
        return view('ahg-registry::admin.institutions');
    }

    public function adminInstitutionUsers(int $id)
    {
        return view('ahg-registry::admin.institution-users', compact('id'));
    }

    public function adminVendors()
    {
        return view('ahg-registry::admin.vendors');
    }

    public function adminSoftware()
    {
        return view('ahg-registry::admin.software');
    }

    public function adminStandards()
    {
        return view('ahg-registry::admin.standards');
    }

    public function adminStandardEdit(int $id)
    {
        return view('ahg-registry::admin.standard-edit', compact('id'));
    }

    public function adminDropdowns()
    {
        return view('ahg-registry::admin.dropdowns');
    }

    public function adminDropdownEdit(int $id)
    {
        return view('ahg-registry::admin.dropdown-edit', compact('id'));
    }

    public function adminBlog()
    {
        return view('ahg-registry::admin.blog');
    }

    public function adminDiscussions()
    {
        return view('ahg-registry::admin.discussions');
    }

    public function adminReviews()
    {
        return view('ahg-registry::admin.reviews');
    }

    public function adminNewsletters()
    {
        return view('ahg-registry::admin.newsletters');
    }

    public function adminNewsletterForm(int $id = null)
    {
        return view('ahg-registry::admin.newsletter-form', compact('id'));
    }

    public function adminSubscribers()
    {
        return view('ahg-registry::admin.subscribers');
    }

    public function adminEmail()
    {
        return view('ahg-registry::admin.email');
    }

    public function adminImport()
    {
        return view('ahg-registry::admin.import');
    }

    public function adminSync()
    {
        return view('ahg-registry::admin.sync');
    }

    public function adminSettings()
    {
        return view('ahg-registry::admin.settings');
    }

    public function adminFooter()
    {
        return view('ahg-registry::admin.footer');
    }

    public function adminSetupGuides()
    {
        return view('ahg-registry::admin.setup-guides');
    }

    public function adminErd()
    {
        return view('ahg-registry::admin.erd');
    }

    public function adminErdEdit(int $id)
    {
        return view('ahg-registry::admin.erd-edit', compact('id'));
    }

    public function adminExtensionEdit(int $id)
    {
        return view('ahg-registry::admin.extension-edit', compact('id'));
    }
}
