<?php
require_once 'conn.php';

header('Content-Type: application/json');

$protein = isset($_POST['protein']) ? trim($_POST['protein']) : '';
$communityInput = isset($_POST['community']) ? $_POST['community'] : null;

// Parse the community input (supports single strings or multi-select arrays)
$selectedComms = [];
if (is_array($communityInput)) {
    $selectedComms = array_filter(array_map('trim', $communityInput));
    $selectedComms = array_slice($selectedComms, 0, 2); // Enforce comparison limit of 2
} elseif (is_string($communityInput) && trim($communityInput) !== '') {
    $selectedComms = [trim($communityInput)];
}

$communities = [];
$table = 'ppi_module_new';

try {
    // 1. Fetch communities and their member genes from ppi_module_new
    if (!empty($selectedComms)) {
        // Fetch selected communities for comparison
        $placeholders = implode(',', array_fill(0, count($selectedComms), '?'));
        $stmt = $conn->prepare("SELECT CD_CommunityName, CD_AnnotatedMembers, CD_MemberList 
                                FROM $table 
                                WHERE CD_CommunityName IN ($placeholders)");
        $types = str_repeat('s', count($selectedComms));
        $stmt->bind_param($types, ...$selectedComms);
    } elseif ($protein !== '') {
        // Filter communities containing the chosen gene/protein (max 2 for speed)
        $stmt = $conn->prepare("SELECT CD_CommunityName, CD_AnnotatedMembers, CD_MemberList 
                                FROM $table 
                                WHERE CD_AnnotatedMembers LIKE ? 
                                   OR CD_MemberList LIKE ? 
                                LIMIT 2");
        $like = "%" . $protein . "%";
        $stmt->bind_param("ss", $like, $like);
    } else {
        // Fallback default: Load the top 2 largest communities
        $stmt = $conn->prepare("SELECT CD_CommunityName, CD_AnnotatedMembers, CD_MemberList 
                                FROM $table 
                                WHERE CD_CommunityName IS NOT NULL 
                                  AND CD_CommunityName != '' 
                                  AND LOWER(TRIM(CD_CommunityName)) NOT IN ('(none)', 'na', 'n/a', 'none')
                                ORDER BY CD_MemberList_Size DESC 
                                LIMIT 2");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $commName = trim($row['CD_CommunityName'] ?? '');
        if (empty($commName)) {
            continue;
        }

        // Aggregate genes from both Annotated Members and Member List columns
        $memberText = ($row['CD_AnnotatedMembers'] ?? '') . ' ' . ($row['CD_MemberList'] ?? '');
        
        // Parse gene symbols split by spaces, commas, semicolons, tabs, pipes, or newlines
        $parts = preg_split('/[\s,;\t|\n\r]+/', $memberText);
        $members = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (!empty($p) && strtolower($p) !== 'na' && strtolower($p) !== 'none' && strtolower($p) !== '(none)') {
                $members[$p] = true;
            }
        }
        
        $communities[] = [
            'name' => $commName,
            'members' => array_keys($members)
        ];
    }
    $stmt->close();

    // 2. Construct elements for Vis.js representation
    $nodes = [];
    $edges = [];
    $addedNodes = [];

    // Color definitions for community visual distinction (Community 1 vs Community 2)
    $colorPalette = [
        0 => ['background' => '#ffc107', 'border' => '#e0a800'], // Yellow
        1 => ['background' => '#28a745', 'border' => '#218838']  // Green
    ];
    $colorIndex = 0;

    foreach ($communities as $comm) {
        $commId = 'comm_' . $comm['name'];
        $commColor = $colorPalette[$colorIndex % count($colorPalette)];
        $colorIndex++;
        
        // Generate Community Node (Hexagon)
        if (!isset($addedNodes[$commId])) {
            $nodes[] = [
                'id' => $commId,
                'label' => $comm['name'],
                'shape' => 'hexagon',
                'color' => $commColor,
                'size' => 22,
                'font' => ['face' => 'arial', 'size' => 13, 'bold' => true]
            ];
            $addedNodes[$commId] = true;
        }

        // Generate Gene Nodes from Annotated Members & Member List
        foreach ($comm['members'] as $gene) {
            $geneId = 'gene_' . $gene;
            if (!isset($addedNodes[$geneId])) {
                $nodes[] = [
                    'id' => $geneId,
                    'label' => $gene,
                    'shape' => 'dot',
                    'color' => [
                        'background' => '#17a2b8',
                        'border' => '#117a8b'
                    ],
                    'size' => 10,
                    'font' => ['face' => 'arial', 'size' => 10]
                ];
                $addedNodes[$geneId] = true;
            }

            // Edge mapping gene node to its parent community
            $edges[] = [
                'from' => $commId,
                'to' => $geneId,
                'color' => '#d3d3d3',
                'width' => 1
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'nodes' => $nodes,
        'edges' => $edges,
        'stats' => [
            'total_nodes' => count($nodes),
            'total_edges' => count($edges)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database operation failed: ' . $e->getMessage()
    ]);
}

$conn->close();