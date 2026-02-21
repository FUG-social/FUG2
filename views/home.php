<main id="view-home" class="view active">
    <h2>Hi, <?= htmlspecialchars($_SESSION['user_name']) ?></h2>
    <p id="location-status" style="color:green;">Locating...</p>
    
    <hr>
    <h3>Update Status</h3>
    <input type="text" id="my-activity-input" style="width:100%" placeholder="What are you doing?" value="<?= htmlspecialchars($_SESSION['user_activity'] ?? '') ?>">

    <h3>Interests (Comma Separated)</h3>
    <input type="text" id="my-interests-input" style="width:100%" placeholder="e.g. Cricket, Chess, Coffee" value="<?= htmlspecialchars($_SESSION['user_interests'] ?? '') ?>">
    <br><br>
    
    <button id="save-profile-btn">Save Profile</button>
    <span id="save-status-indicator" style="color:green; margin-left:10px;"></span>
</main>
