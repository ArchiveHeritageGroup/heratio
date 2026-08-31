<?php

namespace AhgLabel\Controllers;

use AhgCore\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Controller;

/**
 * Storage containers: what is in a box, and labels to put on it.
 *
 * A QR on a storage label has to resolve to something, and until now there was
 * nowhere for it to go - `physical_object` had an API endpoint and a reporting
 * aggregate, but no page. Scanning a box should answer the question a person
 * standing in front of it actually has: what is in here? So the QR points at
 * the container's holdings list.
 */
class ContainerController extends Controller
{
    /** Relation type linking a container (subject) to a record (object). */
    private const HAS_PHYSICAL_OBJECT = 147;

    private function available(): bool
    {
        return Schema::hasTable('physical_object') && Schema::hasTable('physical_object_i18n');
    }

    /** All containers, with a holdings count so empty boxes are obvious. */
    public function index(Request $request)
    {
        if (! $this->available()) {
            abort(404);
        }

        $culture = app()->getLocale();

        $containers = DB::table('physical_object as p')
            ->leftJoin('physical_object_i18n as pi', function ($j) use ($culture) {
                $j->on('pi.id', '=', 'p.id')->where('pi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ty', function ($j) use ($culture) {
                $j->on('ty.id', '=', 'p.type_id')->where('ty.culture', '=', $culture);
            })
            ->select('p.id', 'pi.name', 'pi.location', 'ty.name as type_name')
            ->selectSub(
                DB::table('relation')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('relation.subject_id', 'p.id')
                    ->where('relation.type_id', self::HAS_PHYSICAL_OBJECT),
                'holdings'
            )
            ->orderBy('pi.name')
            ->get();

        return view('label::containers.index', compact('containers'));
    }

    /** One container and the records it holds - what a scanned QR lands on. */
    public function show(int $id)
    {
        if (! $this->available()) {
            abort(404);
        }

        $culture = app()->getLocale();

        $container = DB::table('physical_object as p')
            ->leftJoin('physical_object_i18n as pi', function ($j) use ($culture) {
                $j->on('pi.id', '=', 'p.id')->where('pi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ty', function ($j) use ($culture) {
                $j->on('ty.id', '=', 'p.type_id')->where('ty.culture', '=', $culture);
            })
            ->where('p.id', $id)
            ->select('p.id', 'pi.name', 'pi.location', 'pi.description', 'ty.name as type_name')
            ->first();

        if (! $container) {
            abort(404);
        }

        // Holdings are ACL-filtered, not merely listed: a box label must not
        // become a way to enumerate records the viewer could not open directly.
        $holdings = DB::table('relation as r')
            ->join('information_object as io', 'io.id', '=', 'r.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('r.subject_id', $id)
            ->where('r.type_id', self::HAS_PHYSICAL_OBJECT)
            ->select('io.id', 'io.identifier', 'ioi.title', 's.slug')
            ->orderBy('io.identifier')
            ->get()
            ->filter(fn ($h) => \AhgCore\Services\AclService::check((object) ['id' => $h->id], 'read'))
            ->values();

        $qr = QrCodeService::dataUri(url('/admin/storage/container/'.$id), 200);

        return view('label::containers.show', compact('container', 'holdings', 'qr'));
    }

    /** Printable labels for the selected containers. */
    public function print(Request $request)
    {
        if (! $this->available()) {
            abort(404);
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'required|integer',
        ]);

        $culture = app()->getLocale();

        $labels = DB::table('physical_object as p')
            ->leftJoin('physical_object_i18n as pi', function ($j) use ($culture) {
                $j->on('pi.id', '=', 'p.id')->where('pi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ty', function ($j) use ($culture) {
                $j->on('ty.id', '=', 'p.type_id')->where('ty.culture', '=', $culture);
            })
            ->whereIn('p.id', $validated['ids'])
            ->select('p.id', 'pi.name', 'pi.location', 'ty.name as type_name')
            ->orderBy('pi.name')
            ->get()
            ->map(function ($c) {
                $c->qrUrl = url('/admin/storage/container/'.$c->id);
                $c->qr = QrCodeService::dataUri($c->qrUrl, 120);

                return $c;
            });

        return view('label::containers.labels', compact('labels'));
    }
}
