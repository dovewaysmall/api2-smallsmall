<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    /**
     * Display a listing of all properties (Improved version of your original).
     */
    public function index()
    {
        try {
            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Properties retrieved successfully',
                'data' => $properties,
                'count' => $properties->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified property (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $property = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->where('property_tbl.id', $id)
                ->first();

            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found'
                ], 404);
            }

            // Increment view count
            DB::table('property_tbl')->where('id', $id)->increment('views');

            // Get related properties (same city and property type)
            $relatedProperties = DB::table('property_tbl')
                ->where('city', $property->city)
                ->where('propertyType', $property->propertyType)
                ->where('id', '!=', $id)
                ->where('status', 'available')
                ->select('id', 'propertyTitle', 'price', 'address', 'featuredImg')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Property retrieved successfully',
                'data' => [
                    'property' => $property,
                    'related_properties' => $relatedProperties
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created property (Improved version of your original).
     */
    public function store(Request $request)
    {
        // Enhanced validation rules
        $validator = Validator::make($request->all(), [
            'propertyTitle' => 'required|string|max:255',
            'propertyDescription' => 'required|string|max:2000',
            'propertyType' => 'required|string|in:apartment,house,studio,duplex,bungalow,flat,room',
            'price' => 'required|numeric|min:0',
            'serviceCharge' => 'nullable|numeric|min:0',
            'securityDeposit' => 'nullable|numeric|min:0',
            'securityDepositTerm' => 'nullable|string|max:100',
            'bed' => 'required|integer|min:0',
            'bath' => 'required|integer|min:0',
            'toilet' => 'required|integer|min:0',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'zip' => 'nullable|string|max:20',
            'poster' => 'required|string|max:20',
            'property_owner' => 'nullable|string|max:255',
            'renting_as' => 'nullable|string|in:landlord,agent',
            'furnishing' => 'nullable|string|in:furnished,unfurnished,semi-furnished',
            'paymentPlan' => 'nullable|string|max:20',
            'frequency' => 'nullable|string|in:monthly,quarterly,bi-annually,annually',
            'amenities' => 'nullable|string|max:1000',
            'services' => 'nullable|string|max:1000',
            'available_date' => 'nullable|date|after_or_equal:today',
            'featured_property' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle image uploads
            $imageFolder = null;
            $featuredImg = null;
            
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                $imageFolder = 'property_' . time();
                
                foreach ($images as $index => $image) {
                    $imageName = $imageFolder . '_' . $index . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('property_images/' . $imageFolder, $imageName, 'public');
                    
                    // Set first image as featured
                    if ($index === 0) {
                        $featuredImg = $imageName;
                    }
                }
            }

            // Prepare data for insertion
            $data = [
                'propertyTitle' => $request->propertyTitle,
                'propertyID' => mt_rand(1000000, 99999999999),
                'propertyDescription' => $request->propertyDescription,
                'rentalCondition' => $request->rentalCondition,
                'furnishing' => $request->furnishing ?? 'unfurnished',
                'price' => $request->price,
                'serviceCharge' => $request->serviceCharge ?? 0,
                'securityDeposit' => $request->securityDeposit ?? 0,
                'securityDepositTerm' => $request->securityDepositTerm,
                'verification' => $request->verification ?? 'pending',
                'propertyType' => $request->propertyType,
                'renting_as' => $request->renting_as ?? 'landlord',
                'paymentPlan' => $request->paymentPlan,
                'frequency' => $request->frequency ?? 'monthly',
                'intervals' => $request->intervals,
                'amenities' => $request->amenities,
                'services' => $request->services,
                'bed' => $request->bed,
                'bath' => $request->bath,
                'toilet' => $request->toilet,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'zip' => $request->zip,
                'poster' => $request->poster,
                'property_owner' => $request->property_owner,
                'status' => 'available',
                'imageFolder' => $imageFolder,
                'featuredImg' => $featuredImg,
                'views' => 0,
                'featured_property' => $request->featured_property ?? 0,
                'available_date' => $request->available_date ?? now()->toDateString(),
                'dateOfEntry' => now(),
            ];

            // Insert property and get ID
            $propertyId = DB::table('property_tbl')->insertGetId($data);

            if ($propertyId) {
                // Retrieve the created property with landlord details
                $createdProperty = DB::table('property_tbl')
                        ->select('property_tbl.*')
                    ->where('property_tbl.id', $propertyId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Property created successfully',
                    'data' => $createdProperty,
                    'id' => $propertyId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create property'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified property (Improved version of your original).
     */
    public function update(Request $request, string $id)
    {
        $property = DB::table('property_tbl')->where('id', $id)->first();

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'propertyTitle' => 'sometimes|string|max:255',
            'propertyDescription' => 'sometimes|string|max:2000',
            'propertyType' => 'sometimes|string|in:apartment,house,studio,duplex,bungalow,flat,room',
            'price' => 'sometimes|numeric|min:0',
            'serviceCharge' => 'nullable|numeric|min:0',
            'securityDeposit' => 'nullable|numeric|min:0',
            'bed' => 'sometimes|integer|min:0',
            'bath' => 'sometimes|integer|min:0',
            'toilet' => 'sometimes|integer|min:0',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:available,rented,maintenance,inactive',
            'furnishing' => 'nullable|string|in:furnished,unfurnished,semi-furnished',
            'featured_property' => 'nullable|boolean',
            'available_date' => 'nullable|date',
            'property_owner' => 'nullable|string|max:255',
            'paymentPlan' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'propertyTitle', 'propertyDescription', 'propertyType', 'price', 
                'serviceCharge', 'securityDeposit', 'bed', 'bath', 'toilet',
                'address', 'city', 'state', 'status', 'furnishing', 
                'featured_property', 'available_date', 'amenities', 'services',
                'property_owner', 'paymentPlan'
            ]);

            DB::table('property_tbl')->where('id', $id)->update($updateData);
            
            $updatedProperty = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->where('property_tbl.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Property updated successfully',
                'data' => $updatedProperty
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified property.
     */
    public function destroy(string $id)
    {
        $property = DB::table('property_tbl')->where('id', $id)->first();

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }

        try {
            // Delete property images if they exist
            if ($property->imageFolder) {
                Storage::disk('public')->deleteDirectory('property_images/' . $property->imageFolder);
            }

            DB::table('property_tbl')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Property deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting property',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties by owner/landlord (Improved version of your original).
     */
    public function getByOwner($landlordId)
    {
        try {
            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->where('property_tbl.poster', $landlordId)
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            if ($properties->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No properties found for this owner',
                    'data' => [],
                    'count' => 0
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Owner properties retrieved successfully',
                'data' => $properties,
                'count' => $properties->count(),
                'landlord_id' => $landlordId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties by status.
     */
    public function getByStatus($status)
    {
        $validator = Validator::make(['status' => $status], [
            'status' => 'required|in:available,rented,maintenance,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: available, rented, maintenance, or inactive',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->where('property_tbl.status', $status)
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Properties retrieved successfully',
                'data' => $properties,
                'count' => $properties->count(),
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties by location (city/state).
     */
    public function getByLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = DB::table('property_tbl')
                ->select('property_tbl.*');

            if ($request->city) {
                $query->where('property_tbl.city', 'LIKE', '%' . $request->city . '%');
            }

            if ($request->state) {
                $query->where('property_tbl.state', 'LIKE', '%' . $request->state . '%');
            }

            $properties = $query->where('property_tbl.status', 'available')
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Properties retrieved successfully',
                'data' => $properties,
                'count' => $properties->count(),
                'filters' => [
                    'city' => $request->city,
                    'state' => $request->state
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search properties.
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'property_type' => 'nullable|string',
            'beds' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->input('query');
            
            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->where(function($q) use ($query) {
                    $q->where('property_tbl.propertyTitle', 'LIKE', "%{$query}%")
                      ->orWhere('property_tbl.address', 'LIKE', "%{$query}%")
                      ->orWhere('property_tbl.city', 'LIKE', "%{$query}%")
                      ->orWhere('property_tbl.state', 'LIKE', "%{$query}%");
                })
                ->when($request->min_price, function($q) use ($request) {
                    $q->where('property_tbl.price', '>=', $request->min_price);
                })
                ->when($request->max_price, function($q) use ($request) {
                    $q->where('property_tbl.price', '<=', $request->max_price);
                })
                ->when($request->property_type, function($q) use ($request) {
                    $q->where('property_tbl.propertyType', $request->property_type);
                })
                ->when($request->beds, function($q) use ($request) {
                    $q->where('property_tbl.bed', '>=', $request->beds);
                })
                ->where('property_tbl.status', 'available')
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Property search results',
                'data' => $properties,
                'count' => $properties->count(),
                'search_params' => [
                    'query' => $query,
                    'min_price' => $request->min_price,
                    'max_price' => $request->max_price,
                    'property_type' => $request->property_type,
                    'beds' => $request->beds
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured properties.
     */
    public function getFeatured()
    {
        try {
            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->where('property_tbl.featured_property', 1)
                ->where('property_tbl.status', 'available')
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Featured properties retrieved successfully',
                'data' => $properties,
                'count' => $properties->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving featured properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get property count.
     */
    public function count()
    {
        try {
            $totalCount = DB::table('property_tbl')->count();
            
            return response()->json([
                'success' => true,
                'message' => 'Property count retrieved successfully',
                'count' => $totalCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving property count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get property statistics.
     */
    public function getStats()
    {
        try {
            $totalProperties = DB::table('property_tbl')->count();
            $availableProperties = DB::table('property_tbl')->where('status', 'available')->count();
            $rentedProperties = DB::table('property_tbl')->where('status', 'rented')->count();
            $featuredProperties = DB::table('property_tbl')->where('featured_property', 1)->count();

            $typeStats = DB::table('property_tbl')
                ->select('propertyType', DB::raw('count(*) as count'))
                ->groupBy('propertyType')
                ->orderBy('count', 'desc')
                ->get();

            $stateStats = DB::table('property_tbl')
                ->select('state', DB::raw('count(*) as count'))
                ->groupBy('state')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            $averagePrice = DB::table('property_tbl')
                ->where('status', 'available')
                ->avg('price');

            $priceRange = DB::table('property_tbl')
                ->where('status', 'available')
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Property statistics retrieved successfully',
                'total_properties' => $totalProperties,
                'available_properties' => $availableProperties,
                'rented_properties' => $rentedProperties,
                'featured_properties' => $featuredProperties,
                'average_price' => round($averagePrice, 2),
                'price_range' => $priceRange,
                'property_type_breakdown' => $typeStats,
                'state_breakdown' => $stateStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving property statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties created this week.
     */
    public function getThisWeek()
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->whereDate('property_tbl.dateOfEntry', '>=', $startOfWeek)
                ->whereDate('property_tbl.dateOfEntry', '<=', $endOfWeek)
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            $weekStats = [
                'total_properties' => $properties->count(),
                'available_properties' => $properties->where('status', 'available')->count(),
                'rented_properties' => $properties->where('status', 'rented')->count(),
                'pending_properties' => $properties->where('status', 'pending')->count(),
                'average_price' => $properties->count() > 0 ? round($properties->avg('price'), 2) : 0,
                'highest_price' => $properties->max('price') ?? 0,
                'lowest_price' => $properties->min('price') ?? 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Properties for this week retrieved successfully',
                'data' => $properties,
                'count' => $properties->count(),
                'period' => [
                    'start' => $startOfWeek->format('Y-m-d H:i:s'),
                    'end' => $endOfWeek->format('Y-m-d H:i:s')
                ],
                'week_statistics' => $weekStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving properties for this week',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties created this month.
     */
    public function getThisMonth()
    {
        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->whereDate('property_tbl.dateOfEntry', '>=', $startOfMonth)
                ->whereDate('property_tbl.dateOfEntry', '<=', $endOfMonth)
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            $monthStats = [
                'total_properties' => $properties->count(),
                'available_properties' => $properties->where('status', 'available')->count(),
                'rented_properties' => $properties->where('status', 'rented')->count(),
                'pending_properties' => $properties->where('status', 'pending')->count(),
                'featured_properties' => $properties->where('featured_property', 1)->count(),
                'average_price' => $properties->count() > 0 ? round($properties->avg('price'), 2) : 0,
                'highest_price' => $properties->max('price') ?? 0,
                'lowest_price' => $properties->min('price') ?? 0,
                'property_types' => $properties->groupBy('propertyType')->map->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Properties for this month retrieved successfully',
                'data' => $properties,
                'count' => $properties->count(),
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d H:i:s'),
                    'end' => $endOfMonth->format('Y-m-d H:i:s')
                ],
                'month_statistics' => $monthStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving properties for this month',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties created this year.
     */
    public function getThisYear()
    {
        try {
            $startOfYear = now()->startOfYear();
            $endOfYear = now()->endOfYear();

            $properties = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->whereDate('property_tbl.dateOfEntry', '>=', $startOfYear)
                ->whereDate('property_tbl.dateOfEntry', '<=', $endOfYear)
                ->orderBy('property_tbl.dateOfEntry', 'desc')
                ->get();

            $yearStats = [
                'total_properties' => $properties->count(),
                'available_properties' => $properties->where('status', 'available')->count(),
                'rented_properties' => $properties->where('status', 'rented')->count(),
                'pending_properties' => $properties->where('status', 'pending')->count(),
                'featured_properties' => $properties->where('featured_property', 1)->count(),
                'average_price' => $properties->count() > 0 ? round($properties->avg('price'), 2) : 0,
                'highest_price' => $properties->max('price') ?? 0,
                'lowest_price' => $properties->min('price') ?? 0,
                'property_types' => $properties->groupBy('propertyType')->map->count(),
                'locations' => $properties->groupBy('city')->map->count(),
                'monthly_breakdown' => $properties->groupBy(function($item) {
                    return date('Y-m', strtotime($item->dateOfEntry));
                })->map->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Properties for this year retrieved successfully',
                'data' => $properties,
                'count' => $properties->count(),
                'period' => [
                    'start' => $startOfYear->format('Y-m-d H:i:s'),
                    'end' => $endOfYear->format('Y-m-d H:i:s')
                ],
                'year_statistics' => $yearStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving properties for this year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign owner to a property.
     */
    public function assignOwner(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'property_owner' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $property = DB::table('property_tbl')->where('id', $id)->first();
            
            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found'
                ], 404);
            }

            DB::table('property_tbl')
                ->where('id', $id)
                ->update(['property_owner' => $request->property_owner]);
            
            $updatedProperty = DB::table('property_tbl')
                ->select('property_tbl.*')
                ->where('property_tbl.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Property owner assigned successfully',
                'data' => $updatedProperty
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while assigning property owner',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}