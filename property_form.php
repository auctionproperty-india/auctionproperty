<div class="row g-3">
    <!-- ===== 1. TITLE & PRICE ===== -->
    <div class="col-md-12">
        <h6 class="border-bottom pb-2 fw-bold text-primary"><i class="fas fa-tag me-2"></i>Basic Details</h6>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" id="edit_title" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Reserve Price (₹) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Price per Sq Ft</label>
        <input type="number" step="0.01" name="reserve_price_per_sqft" id="edit_reserve_price_per_sqft" class="form-control">
    </div>

    <!-- ===== 2. BORROWER & BANK ===== -->
    <div class="col-md-12">
        <h6 class="border-bottom pb-2 fw-bold text-primary"><i class="fas fa-user me-2"></i>Borrower & Bank</h6>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Borrower Name</label>
        <input type="text" name="borrower_name" id="edit_borrower_name" class="form-control" placeholder="e.g. Krishna Yuvraj">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Bank Name</label>
        <input type="text" name="bank_name" id="edit_bank_name" class="form-control" placeholder="e.g. HomeFirst Finance">
    </div>

    <!-- ===== 3. PROPERTY DETAILS ===== -->
    <div class="col-md-12">
        <h6 class="border-bottom pb-2 fw-bold text-primary"><i class="fas fa-building me-2"></i>Property Details</h6>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Property Type</label>
        <select name="type" id="edit_type" class="form-control">
            <option value="Flat">Flat</option>
            <option value="Plot">Plot</option>
            <option value="Shop">Shop</option>
            <option value="Land">Land</option>
            <option value="House">House</option>
            <option value="Row House">Row House</option>
            <option value="Bungalow">Bungalow</option>
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label fw-semibold">Address / Location <span class="text-danger">*</span></label>
        <input type="text" name="location" id="edit_location" class="form-control" required placeholder="Full address...">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Possession</label>
        <select name="possession_type" id="edit_possession_type" class="form-control">
            <option value="Physical">Physical</option>
            <option value="Symbolic">Symbolic</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Locality</label>
        <input type="text" name="locality" id="edit_locality" class="form-control" placeholder="Rau, Indore">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
        <input type="text" name="city" id="edit_city" class="form-control" required placeholder="Indore">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">State</label>
        <input type="text" name="state" id="edit_state" class="form-control" placeholder="Madhya Pradesh">
    </div>

    <!-- ===== 4. AUCTION FINANCIALS ===== -->
    <div class="col-md-12">
        <h6 class="border-bottom pb-2 fw-bold text-primary"><i class="fas fa-gavel me-2"></i>Auction Financials</h6>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">EMD Amount (₹)</label>
        <input type="number" step="0.01" name="emd_amount" id="edit_emd_amount" class="form-control" placeholder="108000">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Bid Increment (₹)</label>
        <input type="number" step="0.01" name="bid_increment" id="edit_bid_increment" class="form-control" placeholder="10000">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Area (Sq Ft)</label>
        <input type="number" step="0.01" name="sqft" id="edit_sqft" class="form-control" placeholder="e.g. 1200">
    </div>

    <!-- ===== 5. DATES & TIMES ===== -->
    <div class="col-md-12">
        <h6 class="border-bottom pb-2 fw-bold text-primary"><i class="fas fa-clock me-2"></i>Auction Schedule</h6>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">EMD Submission Deadline</label>
        <input type="text" name="emd_deadline" id="edit_emd_deadline" class="form-control" placeholder="Thu, 25 Jun 2026 05:00 PM">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Auction Start Date & Time</label>
        <input type="text" name="auction_start_time" id="edit_auction_start_time" class="form-control" placeholder="Sat, 27 Jun 2026 11:00 AM">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Auction End Date & Time</label>
        <input type="text" name="auction_end_time" id="edit_auction_end_time" class="form-control" placeholder="Sat, 27 Jun 2026 02:00 PM">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Auction Date (DD/MM/YYYY)</label>
        <input type="text" name="auction_date" id="edit_auction_date" class="form-control" placeholder="e.g. 27/06/2026">
    </div>

    <!-- ===== 6. CONTACT & IMAGE ===== -->
    <div class="col-md-12">
        <h6 class="border-bottom pb-2 fw-bold text-primary"><i class="fas fa-phone me-2"></i>Contact & Media</h6>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Contact Number</label>
        <input type="text" name="contact_number" id="edit_contact_number" class="form-control" value="<?= $default_contact ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Upload Image</label>
        <div id="currentImagePreview" style="display:none; margin-bottom:10px;">
            <img id="currentImage" src="" style="max-height:120px; border-radius:10px; border:1px solid #ddd;">
        </div>
        <input type="file" name="image_file" id="edit_image_file" class="form-control" accept="image/*">
        <small id="imageHelpText">Leave empty to auto-generate a 4K social card.</small>
    </div>
</div>
