<?php
/**
 * Advanced Prescriptive Analytics Engine for FeedbackIQ
 * Uses statistical analysis and pattern recognition to generate actionable insights
 */

// Advanced recommendation engine
function generate_advanced_recommendations($conn, $business_id, $total_feedback, $avg_rating, $recent_feedback, $distribution, $categories, $trend_data) {
    $recommendations = [];
    
    // 1. Overall Performance Assessment
    if ($total_feedback > 0) {
        $performance_tier = calculate_performance_tier($avg_rating, $total_feedback);
        $recommendations[] = [
            'type' => $performance_tier['type'],
            'title' => $performance_tier['title'],
            'description' => $performance_tier['description'],
            'priority' => $performance_tier['priority'],
            'metric_impact' => 'overall'
        ];
    }
    
    // 2. Rating Distribution Analysis (Gini coefficient for inequality)
    if (!empty($distribution) && count($distribution) >= 3) {
        $dist_analysis = analyze_rating_distribution($distribution, $total_feedback);
        if ($dist_analysis['issue'] !== 'none') {
            $recommendations[] = [
                'type' => $dist_analysis['type'],
                'title' => $dist_analysis['title'],
                'description' => $dist_analysis['description'],
                'priority' => $dist_analysis['priority'],
                'metric_impact' => 'rating_consistency'
            ];
        }
    }
    
    // 3. Category Performance Deep Dive
    if (!empty($categories)) {
        $category_insights = analyze_categories_deep($categories, $total_feedback);
        foreach ($category_insights as $insight) {
            $recommendations[] = $insight;
        }
    }
    
    // 4. Trend Analysis with Velocity
    if (!empty($trend_data) && count($trend_data) >= 7) {
        $trend_insights = analyze_trends_advanced($trend_data);
        foreach ($trend_insights as $insight) {
            $recommendations[] = $insight;
        }
    }
    
    // 5. Recent Engagement Health Check
    if ($total_feedback > 20) {
        $engagement_analysis = analyze_engagement_health($recent_feedback, $total_feedback);
        if ($engagement_analysis['needs_attention']) {
            $recommendations[] = [
                'type' => $engagement_analysis['type'],
                'title' => $engagement_analysis['title'],
                'description' => $engagement_analysis['description'],
                'priority' => $engagement_analysis['priority'],
                'metric_impact' => 'engagement'
            ];
        }
    }
    
    // 6. Service Quality Matrix (if service category exists)
    $service_analysis = analyze_service_quality($categories, $distribution);
    if ($service_analysis) {
        $recommendations[] = $service_analysis;
    }
    
    // 7. Statistical Confidence Check
    if ($total_feedback > 0 && $total_feedback < 30) {
        $recommendations[] = [
            'type' => 'info',
            'title' => 'Build Statistical Significance',
            'description' => "You have $total_feedback feedback responses. For more reliable insights, aim for at least 30 responses. Continue promoting your feedback QR code.",
            'priority' => 'medium',
            'metric_impact' => 'data_quality'
        ];
    }
    
    // Sort by priority
    $priority_order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
    usort($recommendations, function($a, $b) use ($priority_order) {
        return $priority_order[$a['priority']] - $priority_order[$b['priority']];
    });
    
    return $recommendations;
}

// Calculate performance tier based on rating and volume
function calculate_performance_tier($avg_rating, $total_feedback) {
    $confidence_multiplier = min(1.0, $total_feedback / 100); // More feedback = more confidence
    
    if ($avg_rating >= 4.5 && $total_feedback >= 50) {
        return [
            'type' => 'success',
            'title' => 'Outstanding Performance',
            'description' => 'Your business is in the top tier with exceptional ratings. Consider leveraging testimonials and case studies for marketing.',
            'priority' => 'low'
        ];
    } elseif ($avg_rating >= 4.0 && $total_feedback >= 30) {
        return [
            'type' => 'success',
            'title' => 'Strong Performance',
            'description' => 'Consistently good ratings indicate solid customer satisfaction. Focus on maintaining standards and gathering more feedback.',
            'priority' => 'low'
        ];
    } elseif ($avg_rating >= 3.5) {
        return [
            'type' => 'info',
            'title' => 'Good but Room for Improvement',
            'description' => 'Your ratings are above average but there\'s opportunity to reach excellence. Identify specific areas for enhancement.',
            'priority' => 'medium'
        ];
    } elseif ($avg_rating >= 3.0) {
        return [
            'type' => 'warning',
            'title' => 'Performance Needs Attention',
            'description' => 'Your average rating suggests inconsistent customer experiences. Implement systematic improvements in service delivery.',
            'priority' => 'high'
        ];
    } else {
        return [
            'type' => 'warning',
            'title' => 'Critical Improvement Needed',
            'description' => 'Low ratings indicate systemic issues. Immediate action required: review operations, train staff, and implement quality controls.',
            'priority' => 'critical'
        ];
    }
}

// Analyze rating distribution for patterns
function analyze_rating_distribution($distribution, $total_feedback) {
    $ratings = array_keys($distribution);
    $counts = array_values($distribution);
    
    // Calculate polarization (bimodal distribution check)
    $low_ratings = isset($distribution[1]) + isset($distribution[2]);
    $high_ratings = isset($distribution[4]) + isset($distribution[5]);
    $mid_ratings = isset($distribution[3]) ? $distribution[3] : 0;
    
    $low_count = 0;
    $high_count = 0;
    foreach ($distribution as $rating => $count) {
        if ($rating <= 2) $low_count += $count;
        if ($rating >= 4) $high_count += $count;
    }
    
    // Check for polarized opinions (lots of 5s and 1s, few middles)
    $polarization_ratio = ($low_count + $high_count) / $total_feedback;
    
    if ($polarization_ratio > 0.7 && $mid_ratings < ($total_feedback * 0.15)) {
        return [
            'type' => 'info',
            'title' => 'Polarized Customer Opinions',
            'description' => 'Customers have strong opinions either way. This suggests your service excels for some but misses for others. Identify what differentiates satisfied vs dissatisfied customers.',
            'priority' => 'medium',
            'issue' => 'polarization'
        ];
    }
    
    // Check for negative skew
    $negative_percentage = $low_count / $total_feedback;
    if ($negative_percentage > 0.25) {
        return [
            'type' => 'warning',
            'title' => 'High Negative Feedback Rate',
            'description' => round($negative_percentage * 100) . '% of feedback is negative (1-2 stars). This exceeds the healthy threshold of 20%. Investigate root causes immediately.',
            'priority' => 'high',
            'issue' => 'negative_skew'
        ];
    }
    
    // Check for consistency
    $std_dev = calculate_std_dev($distribution, $total_feedback);
    if ($std_dev < 0.8 && count($distribution) > 1) {
        return [
            'type' => 'success',
            'title' => 'Consistent Customer Experience',
            'description' => 'Low rating variance indicates you deliver consistent service quality. This reliability is a competitive advantage.',
            'priority' => 'low',
            'issue' => 'none'
        ];
    }
    
    return ['issue' => 'none'];
}

// Deep category analysis
function analyze_categories_deep($categories, $total_feedback) {
    $insights = [];
    
    foreach ($categories as $cat) {
        $category_name = ucfirst($cat['category']);
        $count = $cat['count'];
        $avg_rating = $cat['avg_rating'];
        $percentage = ($count / $total_feedback) * 100;
        
        // High volume + Low rating = Critical
        if ($count > ($total_feedback * 0.25) && $avg_rating < 3.0) {
            $insights[] = [
                'type' => 'warning',
                'title' => "Critical: $category_name Issues",
                'description' => "$category_name represents " . round($percentage) . "% of feedback but has critically low ratings (" . round($avg_rating, 2) . "/5.0). This requires immediate management attention and process review.",
                'priority' => 'critical',
                'metric_impact' => 'category_' . strtolower($cat['category'])
            ];
        }
        // High volume + Good rating = Strength
        elseif ($count > ($total_feedback * 0.3) && $avg_rating >= 4.0) {
            $insights[] = [
                'type' => 'success',
                'title' => "$category_name Excellence",
                'description' => "$category_name is a key strength with " . round($percentage) . "% of total feedback and strong ratings (" . round($avg_rating, 2) . "/5.0). Document best practices here and apply to other areas.",
                'priority' => 'low',
                'metric_impact' => 'category_' . strtolower($cat['category'])
            ];
        }
        // Improving trend
        elseif ($avg_rating >= 3.5 && $avg_rating < 4.0 && $count > ($total_feedback * 0.15)) {
            $insights[] = [
                'type' => 'info',
                'title' => "$category_name Improvement Opportunity",
                'description' => "$category_name shows moderate performance (" . round($avg_rating, 2) . "/5.0) with significant volume (" . round($percentage) . "%). Targeted improvements here could significantly boost overall satisfaction.",
                'priority' => 'medium',
                'metric_impact' => 'category_' . strtolower($cat['category'])
            ];
        }
    }
    
    return $insights;
}

// Advanced trend analysis with velocity
function analyze_trends_advanced($trend_data) {
    $insights = [];
    
    // Calculate week-over-week change
    $recent_days = array_slice($trend_data, -7);
    $previous_days = array_slice($trend_data, -14, 7);
    
    if (count($recent_days) >= 5 && count($previous_days) >= 5) {
        $recent_avg = array_sum(array_column($recent_days, 'count')) / count($recent_days);
        $previous_avg = array_sum(array_column($previous_days, 'count')) / count($previous_days);
        
        $growth_rate = (($recent_avg - $previous_avg) / $previous_avg) * 100;
        
        if ($growth_rate > 30) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Rapid Feedback Growth',
                'description' => "Feedback volume increased by " . round($growth_rate) . "% compared to previous week. Your engagement strategies are working effectively.",
                'priority' => 'low',
                'metric_impact' => 'trend_growth'
            ];
        } elseif ($growth_rate < -30) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Declining Engagement',
                'description' => "Feedback volume decreased by " . abs(round($growth_rate)) . "% compared to previous week. Review QR code visibility and customer touchpoints.",
                'priority' => 'high',
                'metric_impact' => 'trend_decline'
            ];
        }
        
        // Rating trend
        $recent_rating_avg = array_sum(array_column($recent_days, 'avg_rating')) / count($recent_days);
        $previous_rating_avg = array_sum(array_column($previous_days, 'avg_rating')) / count($previous_days);
        
        if ($recent_rating_avg > $previous_rating_avg + 0.3) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Improving Satisfaction Trend',
                'description' => "Average ratings trending upward (" . round($recent_rating_avg, 2) . " vs " . round($previous_rating_avg, 2) . "). Recent improvements are resonating with customers.",
                'priority' => 'low',
                'metric_impact' => 'rating_trend_positive'
            ];
        } elseif ($recent_rating_avg < $previous_rating_avg - 0.3) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Declining Satisfaction Trend',
                'description' => "Average ratings trending downward (" . round($recent_rating_avg, 2) . " vs " . round($previous_rating_avg, 2) . "). Investigate recent changes or issues.",
                'priority' => 'high',
                'metric_impact' => 'rating_trend_negative'
            ];
        }
    }
    
    return $insights;
}

// Engagement health analysis
function analyze_engagement_health($recent_feedback, $total_feedback) {
    $expected_weekly = $total_feedback / 4; // Assuming 4 weeks of data
    $ratio = $recent_feedback / $expected_weekly;
    
    if ($ratio < 0.5) {
        return [
            'type' => 'warning',
            'title' => 'Low Recent Engagement',
            'description' => "Recent feedback ({$recent_feedback}) is less than 50% of expected weekly average (" . round($expected_weekly) . "). Refresh your feedback collection strategy.",
            'priority' => 'high',
            'needs_attention' => true
        ];
    } elseif ($ratio > 1.5) {
        return [
            'type' => 'success',
            'title' => 'High Engagement Momentum',
            'description' => "Recent feedback ({$recent_feedback}) exceeds expected average by " . round(($ratio - 1) * 100) . "%. Strong customer engagement right now.",
            'priority' => 'low',
            'needs_attention' => false
        ];
    }
    
    return ['needs_attention' => false];
}

// Service quality matrix
function analyze_service_quality($categories, $distribution) {
    $service_keywords = ['service', 'staff', 'support', 'customer service'];
    
    foreach ($categories as $cat) {
        if (in_array(strtolower($cat['category']), $service_keywords)) {
            $service_rating = $cat['avg_rating'];
            $service_count = $cat['count'];
            
            // Calculate overall rating excluding service
            $total_weighted = 0;
            $total_count = 0;
            foreach ($categories as $c) {
                if (strtolower($c['category']) !== strtolower($cat['category'])) {
                    $total_weighted += $c['avg_rating'] * $c['count'];
                    $total_count += $c['count'];
                }
            }
            
            $other_avg = $total_count > 0 ? $total_weighted / $total_count : 0;
            
            if ($service_rating < $other_avg - 0.5 && $service_count > 10) {
                return [
                    'type' => 'warning',
                    'title' => 'Service Quality Gap',
                    'description' => "Service-related feedback (" . round($service_rating, 2) . "/5.0) rates significantly lower than other areas (" . round($other_avg, 2) . "/5.0). Prioritize customer service training and process improvements.",
                    'priority' => 'high',
                    'metric_impact' => 'service_gap'
                ];
            }
        }
    }
    
    return null;
}

// Helper function to calculate standard deviation
function calculate_std_dev($distribution, $total) {
    $mean = 0;
    foreach ($distribution as $rating => $count) {
        $mean += $rating * $count;
    }
    $mean /= $total;
    
    $variance = 0;
    foreach ($distribution as $rating => $count) {
        $variance += pow($rating - $mean, 2) * $count;
    }
    $variance /= $total;
    
    return sqrt($variance);
}
