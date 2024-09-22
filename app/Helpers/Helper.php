<?php


if (!function_exists('unsigned')) {
    function unsigned($str, $strtolower = 0)
    {
        $marTViet = array(
            "à", "á", "ạ", "ả", "ã", "â", "ầ", "ấ", "ậ", "ẩ", "ẫ", "ă", "ằ", "ắ", "ặ", "ẳ", "ẵ",
            "è", "é", "ẹ", "ẻ", "ẽ", "ê", "ề", "ế", "ệ", "ể", "ễ",
            "ì", "í", "ị", "ỉ", "ĩ",
            "ò", "ó", "ọ", "ỏ", "õ", "ô", "ồ", "ố", "ộ", "ổ", "ỗ", "ơ", "ờ", "ớ", "ợ", "ở", "ỡ",
            "ù", "ú", "ụ", "ủ", "ũ", "ư", "ừ", "ứ", "ự", "ử", "ữ",
            "ỳ", "ý", "ỵ", "ỷ", "ỹ",
            "đ",
            "À", "Á", "Ạ", "Ả", "Ã", "Â", "Ầ", "Ấ", "Ậ", "Ẩ", "Ẫ", "Ă", "Ằ", "Ắ", "Ặ", "Ẳ", "Ẵ",
            "È", "É", "Ẹ", "Ẻ", "Ẽ", "Ê", "Ề", "Ế", "Ệ", "Ể", "Ễ",
            "Ì", "Í", "Ị", "Ỉ", "Ĩ",
            "Ò", "Ó", "Ọ", "Ỏ", "Õ", "Ô", "Ồ", "Ố", "Ộ", "Ổ", "Ỗ", "Ơ", "Ờ", "Ớ", "Ợ", "Ở", "Ỡ",
            "Ù", "Ú", "Ụ", "Ủ", "Ũ", "Ư", "Ừ", "Ứ", "Ự", "Ử", "Ữ",
            "Ỳ", "Ý", "Ỵ", "Ỷ", "Ỹ",
            "Đ");
        $marKoDau = array(
            "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a",
            "e", "e", "e", "e", "e", "e", "e", "e", "e", "e", "e",
            "i", "i", "i", "i", "i",
            "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o",
            "u", "u", "u", "u", "u", "u", "u", "u", "u", "u", "u",
            "y", "y", "y", "y", "y",
            "d",
            "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A",
            "E", "E", "E", "E", "E", "E", "E", "E", "E", "E", "E",
            "I", "I", "I", "I", "I",
            "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O",
            "U", "U", "U", "U", "U", "U", "U", "U", "U", "U", "U",
            "Y", "Y", "Y", "Y", "Y",
            "D");
        if ($strtolower != 0) {
            $str = strtolower(str_replace($marTViet, $marKoDau, $str));
        } else {
            $str = str_replace($marTViet, $marKoDau, $str);
        }
        return $str;
    }
}

if (!function_exists(function: 'checkType')) {
    function checkType($str)
    {
        $res = '';
        $str = unsigned($str, 1);

        $str = str_replace('tran van minh chuyen tien', '', $str);
        //Kiểm tra chuỗi có 1 trong các cụm từ này hay không
        // Danh sách từ khóa và loại giao dịch tương ứng
        $types = [
            'com' => 'COM',
            'an' => 'AN',
            'an uong' => 'ANUONG',
            'anuong' => 'ANUONG',
            'congdong' => 'CONGDONG',
            'cong dong' => 'CONGDONG',
            'ruttien' => 'RUTTIEN',
            'gui xe' => 'GUIXE',
            'veque' => 'VEQUE',
            'xang' => 'XANG',
            'tuan' => 'Dịch vụ',
            'tuyen iu' => 'TUYEN',
            'giai tri' => 'GIAITRI',
            'giaitri' => 'GIAITRI',
            'sieuthi' => 'SIEUTHI',
            'thuc an' => 'THUCAN',
            'thucan' => 'THUCAN',
            'hen xui' => 'HENXUI',
            'chuyen khoan' => 'CHUYENKHOAN',
            'chuyenkhoan' => 'CHUYENKHOAN',
            'ship' => 'SHIP',
            'cat toc' => 'CATTTOC',
            'cattoc' => 'CATTTOC',
            'thuoc' => 'THUOC',
            'dien thoai' => 'DIENTHOAI',
            'dienthoai' => 'DIENTHOAI',
            'sinh nhat' => 'SINHNHAT',
            'sinhnhat' => 'SINHNHAT',
            'cuoi' => 'CUOI',
            'mua sam' => 'MUASAM',
            'muasam' => 'MUASAM',
        ];

        // Tìm từ khóa trong chuỗi
        foreach ($types as $keyword => $type) {
            if (strpos($str, $keyword) !== false) {
                return $type;
            }
        }

        // Trả về 'Khác' nếu không tìm thấy từ khóa nào khớp
        return 'ORTHER';
    }
}
