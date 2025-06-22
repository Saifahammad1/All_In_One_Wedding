<?php
session_start();
require_once 'config.php'; // Include the config file

// classes/User.php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $bride_name;
    public $groom_name;
    public $email;
    public $password;
    public $phone;
    public $wedding_date;
    public $budget;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET bride_name=:bride_name, groom_name=:groom_name, email=:email, 
                    password=:password, phone=:phone, wedding_date=:wedding_date, 
                    budget=:budget, created_at=:created_at";

        $stmt = $this->conn->prepare($query);

        $this->bride_name = htmlspecialchars(strip_tags($this->bride_name));
        $this->groom_name = htmlspecialchars(strip_tags($this->groom_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->wedding_date = htmlspecialchars(strip_tags($this->wedding_date));
        $this->budget = htmlspecialchars(strip_tags($this->budget));
        $this->created_at = date('Y-m-d H:i:s');

        $stmt->bindParam(":bride_name", $this->bride_name);
        $stmt->bindParam(":groom_name", $this->groom_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":wedding_date", $this->wedding_date);
        $stmt->bindParam(":budget", $this->budget);
        $stmt->bindParam(":created_at", $this->created_at);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function login() {
        $query = "SELECT id, bride_name, groom_name, email, password, phone, wedding_date, budget 
                FROM " . $this->table_name . " WHERE email = :email LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row && password_verify($this->password, $row['password'])) {
            $this->id = $row['id'];
            $this->bride_name = $row['bride_name'];
            $this->groom_name = $row['groom_name'];
            $this->phone = $row['phone'];
            $this->wedding_date = $row['wedding_date'];
            $this->budget = $row['budget'];
            return true;
        }
        return false;
    }

    public function getDashboardStats() {
        $stats = array();
        
        // Get budget stats
        $budget_query = "SELECT COALESCE(SUM(amount), 0) as total_spent FROM expenses WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($budget_query);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
        $budget_result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['budget'] = array(
            'total' => $this->budget,
            'spent' => $budget_result['total_spent'],
            'remaining' => $this->budget - $budget_result['total_spent']
        );

        // Get guest stats
        $guest_query = "SELECT 
                        COUNT(*) as total_invited,
                        SUM(CASE WHEN rsvp_status = 'confirmed' THEN guest_count ELSE 0 END) as confirmed,
                        SUM(CASE WHEN rsvp_status = 'declined' THEN guest_count ELSE 0 END) as declined,
                        SUM(CASE WHEN rsvp_status = 'pending' THEN guest_count ELSE 0 END) as pending
                        FROM guests WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($guest_query);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
        $stats['guests'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get task stats
        $task_query = "SELECT 
                       COUNT(*) as total_tasks,
                       SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_tasks
                       FROM tasks WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($task_query);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
        $stats['tasks'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get vendor stats
        $vendor_query = "SELECT 
                        COUNT(*) as total_vendors,
                        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked_vendors
                        FROM user_vendors WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($vendor_query);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
        $stats['vendors'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;
    }
}

// classes/Vendor.php
class Vendor {
    private $conn;
    private $table_name = "vendors";

    public $id;
    public $name;
    public $category;
    public $description;
    public $price_range_min;
    public $price_range_max;
    public $rating;
    public $review_count;
    public $phone;
    public $email;
    public $website;
    public $image_url;
    public $location;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByCategory($category) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE category = :category ORDER BY rating DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category', $category);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY category, rating DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// classes/Guest.php
class Guest {
    private $conn;
    private $table_name = "guests";

    public $id;
    public $user_id;
    public $name;
    public $email;
    public $phone;
    public $group_type;
    public $guest_count;
    public $rsvp_status;
    public $dietary_restrictions;
    public $notes;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id, name=:name, email=:email, phone=:phone,
                    group_type=:group_type, guest_count=:guest_count, 
                    rsvp_status=:rsvp_status, dietary_restrictions=:dietary_restrictions, 
                    notes=:notes, created_at=:created_at";

        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->group_type = htmlspecialchars(strip_tags($this->group_type));
        $this->rsvp_status = 'pending';
        $created_at = date('Y-m-d H:i:s');

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":group_type", $this->group_type);
        $stmt->bindParam(":guest_count", $this->guest_count);
        $stmt->bindParam(":rsvp_status", $this->rsvp_status);
        $stmt->bindParam(":dietary_restrictions", $this->dietary_restrictions);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":created_at", $created_at);

        return $stmt->execute();
    }

    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRSVP($guest_id, $status, $guest_count = null) {
        $query = "UPDATE " . $this->table_name . " 
                SET rsvp_status = :status, rsvp_date = :rsvp_date";
        
        if ($guest_count !== null) {
            $query .= ", guest_count = :guest_count";
        }
        
        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $rsvp_date = date('Y-m-d H:i:s');

        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':rsvp_date', $rsvp_date);
        $stmt->bindParam(':id', $guest_id);
        
        if ($guest_count !== null) {
            $stmt->bindParam(':guest_count', $guest_count);
        }

        return $stmt->execute();
    }
}

// classes/Budget.php
class Budget {
    private $conn;
    private $table_name = "expenses";

    public $id;
    public $user_id;
    public $category;
    public $vendor_name;
    public $amount;
    public $description;
    public $payment_status;
    public $due_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id, category=:category, vendor_name=:vendor_name,
                    amount=:amount, description=:description, payment_status=:payment_status,
                    due_date=:due_date, created_at=:created_at";

        $stmt = $this->conn->prepare($query);

        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->vendor_name = htmlspecialchars(strip_tags($this->vendor_name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->payment_status = 'pending';
        $created_at = date('Y-m-d H:i:s');

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":vendor_name", $this->vendor_name);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":payment_status", $this->payment_status);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":created_at", $created_at);

        return $stmt->execute();
    }

    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBudgetByCategory($user_id) {
        $query = "SELECT category, 
                         SUM(amount) as total_spent,
                         COUNT(*) as expense_count
                  FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  GROUP BY category 
                  ORDER BY total_spent DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePaymentStatus($expense_id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                SET payment_status = :status, paid_date = :paid_date 
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $paid_date = ($status === 'paid') ? date('Y-m-d H:i:s') : null;

        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':paid_date', $paid_date);
        $stmt->bindParam(':id', $expense_id);

        return $stmt->execute();
    }
}

// classes/Task.php
class Task {
    private $conn;
    private $table_name = "tasks";

    public $id;
    public $user_id;
    public $title;
    public $description;
    public $category;
    public $priority;
    public $due_date;
    public $is_completed;
    public $assigned_to;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id, title=:title, description=:description,
                    category=:category, priority=:priority, due_date=:due_date,
                    is_completed=:is_completed, assigned_to=:assigned_to, created_at=:created_at";

        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->priority = htmlspecialchars(strip_tags($this->priority));
        $this->assigned_to = htmlspecialchars(strip_tags($this->assigned_to));
        $this->is_completed = 0;
        $created_at = date('Y-m-d H:i:s');

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":is_completed", $this->is_completed);
        $stmt->bindParam(":assigned_to", $this->assigned_to);
        $stmt->bindParam(":created_at", $created_at);

        return $stmt->execute();
    }

    public function getByUserId($user_id, $filter = 'all') {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id";
        
        if ($filter === 'pending') {
            $query .= " AND is_completed = 0";
        } elseif ($filter === 'completed') {
            $query .= " AND is_completed = 1";
        } elseif ($filter === 'urgent') {
            $query .= " AND priority = 'high' AND is_completed = 0 AND due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)";
        }
        
        $query .= " ORDER BY priority DESC, due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleComplete($task_id) {
        $query = "UPDATE " . $this->table_name . " 
                SET is_completed = CASE WHEN is_completed = 1 THEN 0 ELSE 1 END,
                    completed_date = CASE WHEN is_completed = 0 THEN NOW() ELSE NULL END
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $task_id);
        return $stmt->execute();
    }
}

// classes/Timeline.php
class Timeline {
    private $conn;
    private $table_name = "timeline_events";

    public $id;
    public $user_id;
    public $event_name;
    public $event_time;
    public $duration;
    public $location;
    public $description;
    public $attendees;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id, event_name=:event_name, event_time=:event_time,
                    duration=:duration, location=:location, description=:description,
                    attendees=:attendees, created_at=:created_at";

        $stmt = $this->conn->prepare($query);

        $this->event_name = htmlspecialchars(strip_tags($this->event_name));
        $this->location = htmlspecialchars(strip_tags($this->location));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->attendees = htmlspecialchars(strip_tags($this->attendees));
        $created_at = date('Y-m-d H:i:s');

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":event_name", $this->event_name);
        $stmt->bindParam(":event_time", $this->event_time);
        $stmt->bindParam(":duration", $this->duration);
        $stmt->bindParam(":location", $this->location);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":attendees", $this->attendees);
        $stmt->bindParam(":created_at", $created_at);

        return $stmt->execute();
    }

    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                WHERE user_id = :user_id 
                ORDER BY event_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>